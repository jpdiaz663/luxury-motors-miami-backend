<?php

declare(strict_types=1);

namespace Drupal\lm_notify\EventSubscriber;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\lm_notify\Entity\DispatchLog;
use Drupal\lm_notify\Event\MessageFailedEvent;
use Drupal\lm_notify\Event\MessageSentEvent;
use Drupal\lm_notify\Message;
use Drupal\lm_notify\SendResult;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Persists every send attempt as an internal dispatch log.
 */
final class DispatchLogSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      MessageSentEvent::class => 'onSent',
      MessageFailedEvent::class => 'onFailed',
    ];
  }

  public function onSent(MessageSentEvent $event): void {
    $this->persist($event->message, $event->result);
    $this->logger->info('Notification @key sent to @to via @channel.', [
      '@key' => $event->message->key,
      '@to' => $event->message->to,
      '@channel' => $event->message->channel,
    ]);
  }

  public function onFailed(MessageFailedEvent $event): void {
    $this->persist($event->message, $event->result);
    $this->logger->warning('Notification @key status @status to @to: @error', [
      '@key' => $event->message->key,
      '@status' => $event->result->status,
      '@to' => $event->message->to,
      '@error' => $event->result->error ?? '',
    ]);
  }

  private function persist(Message $message, SendResult $result): void {
    try {
      $storage = $this->entityTypeManager->getStorage('lm_dispatch_log');
      $entity = $storage->create([
        'channel' => mb_substr($message->channel, 0, 32),
        'provider' => mb_substr($result->provider, 0, 64),
        'message_key' => mb_substr($message->key, 0, 64),
        'status' => mb_substr($result->status, 0, 32),
        'recipient' => mb_substr($message->to, 0, 254),
        'subject' => mb_substr($message->subject, 0, 255),
        'payload' => $this->payloadJson($message),
        'error' => $result->error !== NULL ? mb_substr($result->error, 0, 500) : '',
        'ip_hash' => mb_substr((string) $message->ipHash, 0, 64),
      ]);
      if ($entity instanceof DispatchLog) {
        $entity->save();
      }
    }
    catch (EntityStorageException | \Throwable $e) {
      $this->logger->error('Failed to persist dispatch log for @key: @error', [
        '@key' => $message->key,
        '@error' => mb_substr($e->getMessage(), 0, 500),
      ]);
    }
  }

  private function payloadJson(Message $message): string {
    $clean = [];
    foreach ($message->metadata as $key => $value) {
      $safeKey = preg_replace('/[^a-z0-9_]/i', '', (string) $key) ?: 'field';
      $text = trim(strip_tags((string) $value));
      $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text) ?? $text;
      $clean[$safeKey] = mb_substr($text, 0, 4000);
    }
    try {
      return json_encode($clean, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
    catch (\JsonException) {
      return '{}';
    }
  }

}
