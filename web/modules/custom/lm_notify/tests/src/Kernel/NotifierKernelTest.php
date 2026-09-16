<?php

declare(strict_types=1);

namespace Drupal\Tests\lm_notify\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\lm_notify\Adapter\ChannelAdapterInterface;
use Drupal\lm_notify\ChannelResolver;
use Drupal\lm_notify\Decorator\SecurityChannelDecorator;
use Drupal\lm_notify\Message;
use Drupal\lm_notify\Notifier;
use Drupal\lm_notify\SendResult;

/**
 * Notifier persistence and fail-closed SMTP without live Hostinger.
 *
 * @group lm_notify
 */
final class NotifierKernelTest extends KernelTestBase {

  protected static $modules = ['system', 'user', 'field', 'lm_notify'];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('lm_dispatch_log');
    $this->installConfig(['lm_notify']);
  }

  public function testSentMessageIsPersisted(): void {
    $adapter = new class implements ChannelAdapterInterface {
      public function send(Message $message): SendResult {
        return SendResult::sent('hostinger', 'msg-1');
      }
    };
    $result = $this->notifier($adapter)->send($this->message());
    $this->assertTrue($result->ok);

    $storage = $this->container->get('entity_type.manager')->getStorage('lm_dispatch_log');
    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    $this->assertCount(1, $ids);
    $log = $storage->load(reset($ids));
    $this->assertSame(SendResult::SENT, $log->get('status')->value);
    $this->assertSame('contact_inquiry', $log->get('message_key')->value);
    $this->assertStringContainsString('guest@example.com', $log->get('payload')->value);
  }

  public function testFailedMessageIsPersisted(): void {
    $adapter = new class implements ChannelAdapterInterface {
      public function send(Message $message): SendResult {
        return SendResult::failed('smtp timeout');
      }
    };
    $result = $this->notifier($adapter)->send($this->message());
    $this->assertFalse($result->ok);

    $storage = $this->container->get('entity_type.manager')->getStorage('lm_dispatch_log');
    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    $this->assertCount(1, $ids);
    $log = $storage->load(reset($ids));
    $this->assertSame(SendResult::FAILED, $log->get('status')->value);
  }

  public function testUnconfiguredSmtpSkipsWithoutNetwork(): void {
    $result = $this->container->get('lm_notify.notifier')->send($this->message());
    $this->assertFalse($result->ok);
    $this->assertSame(SendResult::SKIPPED_UNCONFIGURED, $result->status);

    $storage = $this->container->get('entity_type.manager')->getStorage('lm_dispatch_log');
    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    $this->assertCount(1, $ids);
    $log = $storage->load(reset($ids));
    $this->assertSame(SendResult::SKIPPED_UNCONFIGURED, $log->get('status')->value);
  }

  public function testInvalidRecipientIsBlockedAndPersisted(): void {
    $result = $this->container->get('lm_notify.notifier')->send($this->message('not-valid'));
    $this->assertSame(SendResult::BLOCKED, $result->status);

    $storage = $this->container->get('entity_type.manager')->getStorage('lm_dispatch_log');
    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    $this->assertCount(1, $ids);
  }

  private function notifier(ChannelAdapterInterface $adapter): Notifier {
    $secure = new SecurityChannelDecorator(
      $adapter,
      $this->container->get('email.validator'),
      $this->container->get('config.factory'),
    );
    return new Notifier(
      new ChannelResolver(['email' => $secure]),
      $this->container->get('event_dispatcher'),
      $this->container->get('logger.channel.lm_notify'),
    );
  }

  private function message(string $to = 'desk@example.com'): Message {
    return new Message(
      channel: 'email',
      to: $to,
      subject: 'New vehicle requirement',
      bodyText: "Name: Test\nEmail: guest@example.com",
      key: 'contact_inquiry',
      replyTo: 'guest@example.com',
      metadata: [
        'name' => 'Test',
        'email' => 'guest@example.com',
        'phone' => '3055550100',
        'message' => 'Weekend rental',
      ],
      ipHash: hash('sha256', '127.0.0.1'),
    );
  }

}
