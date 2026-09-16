<?php

declare(strict_types=1);

namespace Drupal\lm_notify;

use Drupal\lm_notify\Event\MessageFailedEvent;
use Drupal\lm_notify\Event\MessageSendingEvent;
use Drupal\lm_notify\Event\MessageSentEvent;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Dispatches lifecycle events and delegates to the channel adapter.
 */
final class Notifier implements NotifierInterface {

  public function __construct(
    private readonly ChannelResolver $resolver,
    private readonly EventDispatcherInterface $eventDispatcher,
    private readonly LoggerInterface $logger,
  ) {}

  public function send(Message $message): SendResult {
    $sending = new MessageSendingEvent($message);
    $this->eventDispatcher->dispatch($sending);
    if ($sending->isCancelled()) {
      $result = SendResult::blocked($sending->reason() !== '' ? $sending->reason() : 'Send cancelled.');
      $this->eventDispatcher->dispatch(new MessageFailedEvent($message, $result));
      return $result;
    }

    try {
      $result = $this->resolver->get($message->channel)->send($message);
    }
    catch (\InvalidArgumentException $e) {
      $result = SendResult::blocked($e->getMessage());
    }
    catch (\Throwable $e) {
      $this->logger->error('Notification adapter threw for @key: @error', [
        '@key' => $message->key,
        '@error' => mb_substr($e->getMessage(), 0, 500),
      ]);
      $result = SendResult::failed('Notification transport failed.');
    }

    if ($result->ok) {
      $this->eventDispatcher->dispatch(new MessageSentEvent($message, $result));
    }
    else {
      $this->eventDispatcher->dispatch(new MessageFailedEvent($message, $result));
    }

    return $result;
  }

}
