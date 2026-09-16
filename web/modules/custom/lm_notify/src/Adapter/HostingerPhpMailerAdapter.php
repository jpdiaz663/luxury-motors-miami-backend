<?php

declare(strict_types=1);

namespace Drupal\lm_notify\Adapter;

use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\lm_notify\Message;
use Drupal\lm_notify\SendResult;
use PHPMailer\PHPMailer\Exception as PhpMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Log\LoggerInterface;

/**
 * Email via PHPMailer: SMTP (Gmail/Hostinger) or PHP mail().
 */
final class HostingerPhpMailerAdapter implements ChannelAdapterInterface {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly Settings $settings,
    private readonly EmailValidatorInterface $emailValidator,
    private readonly LoggerInterface $logger,
  ) {}

  public function send(Message $message): SendResult {
    $config = $this->configFactory->get('lm_notify.settings')->get('email') ?? [];
    $secrets = $this->settings->get('lm_notify', []);
    if (!is_array($secrets)) {
      $secrets = [];
    }

    $transport = strtolower(trim((string) ($config['transport'] ?? 'hostinger')));
    $fromName = trim((string) ($config['from_name'] ?? 'Luxury Motors'));
    $fromAddress = trim((string) ($config['from_address'] ?? ''));
    if ($fromAddress === '') {
      $fromAddress = trim((string) $this->configFactory->get('system.site')->get('mail'));
    }
    if ($fromAddress === '' || !$this->emailValidator->isValid($fromAddress)) {
      return SendResult::failed('Configured from address is invalid.', in_array($transport, ['php_mail', 'mail', 'php'], TRUE) ? 'php_mail' : 'smtp');
    }

    if (in_array($transport, ['php_mail', 'mail', 'php'], TRUE)) {
      return $this->sendWithPhpMail($message, $fromAddress, $fromName);
    }

    return $this->sendWithSmtp($message, $config, $secrets, $fromAddress, $fromName, $transport);
  }

  /**
   * @param array<string, mixed> $config
   * @param array<string, mixed> $secrets
   */
  private function sendWithSmtp(Message $message, array $config, array $secrets, string $fromAddress, string $fromName, string $transport): SendResult {
    $host = trim((string) ($config['host'] ?? ''));
    $port = (int) ($config['port'] ?? 465);
    $encryption = strtolower(trim((string) ($config['encryption'] ?? 'ssl')));
    if ($encryption === 'starttls') {
      $encryption = 'tls';
    }
    $timeout = (int) ($config['timeout'] ?? 15);
    $username = trim((string) ($secrets['smtp_username'] ?? ''));
    $password = (string) ($secrets['smtp_password'] ?? '');
    $provider = $transport === 'hostinger' ? 'hostinger' : 'smtp';

    if ($username === '') {
      $username = trim((string) ($config['username'] ?? ''));
    }

    if ($host === '' || $username === '' || $password === '') {
      return SendResult::skippedUnconfigured('SMTP credentials or from address are not configured.');
    }
    if (!in_array($encryption, ['ssl', 'tls'], TRUE)) {
      return SendResult::failed('SMTP encryption must be ssl or tls.');
    }
    if ($port < 1 || $port > 65535) {
      return SendResult::failed('SMTP port is invalid.');
    }

    $mailer = $this->createMailer();
    try {
      $mailer->isSMTP();
      $mailer->Host = $host;
      $mailer->SMTPAuth = TRUE;
      $mailer->Username = $username;
      $mailer->Password = $password;
      $mailer->SMTPSecure = $encryption === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
      $mailer->Port = $port;
      $mailer->Timeout = max(5, $timeout);
      $this->applyMessage($mailer, $message, $fromAddress, $fromName);
      $mailer->send();
    }
    catch (PhpMailerException | \Throwable $e) {
      $safe = $this->safeError($e->getMessage(), $password, $username);
      $this->logger->error('SMTP send failed for @key: @error', [
        '@key' => $message->key,
        '@error' => $safe,
      ]);
      return SendResult::failed($safe, $provider);
    }

    return SendResult::sent($provider);
  }

  private function sendWithPhpMail(Message $message, string $fromAddress, string $fromName): SendResult {
    $mailer = $this->createMailer();
    try {
      $mailer->isMail();
      $this->applyMessage($mailer, $message, $fromAddress, $fromName);
      $mailer->send();
    }
    catch (PhpMailerException | \Throwable $e) {
      $safe = $this->safeError($e->getMessage(), '', '');
      $this->logger->error('PHP mail() send failed for @key: @error', [
        '@key' => $message->key,
        '@error' => $safe,
      ]);
      return SendResult::failed($safe, 'php_mail');
    }

    return SendResult::sent('php_mail');
  }

  private function applyMessage(PHPMailer $mailer, Message $message, string $fromAddress, string $fromName): void {
    $mailer->CharSet = PHPMailer::CHARSET_UTF8;
    $mailer->setFrom($fromAddress, $fromName);
    $mailer->addAddress($message->to);
    if ($message->replyTo) {
      $mailer->addReplyTo($message->replyTo);
    }
    $mailer->Subject = $message->subject;
    if ($message->bodyHtml) {
      $mailer->isHTML(TRUE);
      $mailer->Body = $message->bodyHtml;
      $mailer->AltBody = $message->bodyText;
    }
    else {
      $mailer->isHTML(FALSE);
      $mailer->Body = $message->bodyText;
      $mailer->AltBody = $message->bodyText;
    }
  }

  protected function createMailer(): PHPMailer {
    return new PHPMailer(TRUE);
  }

  private function safeError(string $message, string $password, string $username): string {
    $redacted = $message;
    if ($password !== '') {
      $redacted = str_replace($password, '[redacted]', $redacted);
    }
    if ($username !== '') {
      $redacted = str_replace($username, '[redacted]', $redacted);
    }
    $redacted = preg_replace('/[\r\n]+/', ' ', $redacted) ?? $redacted;
    return mb_substr($redacted, 0, 500);
  }

}
