<?php

declare(strict_types=1);

namespace Drupal\lm_notify\Decorator;

use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\lm_notify\Adapter\ChannelAdapterInterface;
use Drupal\lm_notify\Message;
use Drupal\lm_notify\SendResult;

/**
 * Rejects header injection, invalid addresses, and oversized payloads.
 */
final class SecurityChannelDecorator implements ChannelAdapterInterface {

  private const DEFAULT_SUBJECT_MAX = 200;

  private const DEFAULT_BODY_MAX = 20000;

  private const DEFAULT_HTML_MAX = 80000;

  public function __construct(
    private readonly ChannelAdapterInterface $inner,
    private readonly EmailValidatorInterface $emailValidator,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  public function send(Message $message): SendResult {
    $config = $this->configFactory->get('lm_notify.settings')->get('email') ?? [];
    $subjectMax = (int) ($config['subject_max_length'] ?? self::DEFAULT_SUBJECT_MAX);
    $bodyMax = (int) ($config['body_max_length'] ?? self::DEFAULT_BODY_MAX);
    $htmlMax = (int) ($config['html_max_length'] ?? self::DEFAULT_HTML_MAX);

    $violation = $this->violation($message, $subjectMax, $bodyMax, $htmlMax);
    if ($violation !== NULL) {
      return SendResult::blocked($violation);
    }

    return $this->inner->send($message);
  }

  private function violation(Message $message, int $subjectMax, int $bodyMax, int $htmlMax): ?string {
    if ($message->channel === '') {
      return 'Missing notification channel.';
    }
    if ($this->hasLineBreak($message->to) || $this->hasLineBreak($message->subject) || ($message->replyTo !== NULL && $this->hasLineBreak($message->replyTo))) {
      return 'Header injection detected.';
    }
    if (!$this->emailValidator->isValid($message->to)) {
      return 'Invalid recipient address.';
    }
    if ($message->replyTo !== NULL && $message->replyTo !== '' && !$this->emailValidator->isValid($message->replyTo)) {
      return 'Invalid reply-to address.';
    }
    if ($message->subject === '' || mb_strlen($message->subject) > $subjectMax) {
      return 'Invalid subject.';
    }
    if ($message->bodyText === '' || mb_strlen($message->bodyText) > $bodyMax) {
      return 'Invalid body.';
    }
    if ($message->bodyHtml !== NULL && $message->bodyHtml !== '') {
      if (mb_strlen($message->bodyHtml) > $htmlMax) {
        return 'Invalid HTML body.';
      }
      // Word boundary on on* so meta content= and similar attributes are not blocked.
      if (preg_match('/<script|javascript:|\bon\w+\s*=/i', $message->bodyHtml)) {
        return 'Unsafe HTML body.';
      }
    }
    return NULL;
  }

  private function hasLineBreak(string $value): bool {
    return str_contains($value, "\n") || str_contains($value, "\r");
  }

}
