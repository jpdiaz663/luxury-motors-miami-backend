<?php

declare(strict_types=1);

namespace Drupal\Tests\lm_notify\Unit;

use Drupal\Component\Utility\EmailValidator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\lm_notify\Adapter\ChannelAdapterInterface;
use Drupal\lm_notify\Decorator\SecurityChannelDecorator;
use Drupal\lm_notify\Message;
use Drupal\lm_notify\SendResult;
use Drupal\Tests\UnitTestCase;

/**
 * @covers \Drupal\lm_notify\Decorator\SecurityChannelDecorator
 */
final class SecurityChannelDecoratorTest extends UnitTestCase {

  public function testBlocksHeaderInjection(): void {
    $inner = $this->createMock(ChannelAdapterInterface::class);
    $inner->expects($this->never())->method('send');
    $decorator = $this->decorator($inner);

    $result = $decorator->send($this->message(to: "desk@example.com\nBcc: evil@example.com"));
    $this->assertFalse($result->ok);
    $this->assertSame(SendResult::BLOCKED, $result->status);
  }

  public function testBlocksInvalidRecipient(): void {
    $inner = $this->createMock(ChannelAdapterInterface::class);
    $inner->expects($this->never())->method('send');
    $decorator = $this->decorator($inner);

    $result = $decorator->send($this->message(to: 'not-an-email'));
    $this->assertSame(SendResult::BLOCKED, $result->status);
  }

  public function testDelegatesValidMessage(): void {
    $inner = $this->createMock(ChannelAdapterInterface::class);
    $inner->expects($this->once())
      ->method('send')
      ->willReturn(SendResult::sent());
    $decorator = $this->decorator($inner);

    $result = $decorator->send($this->message());
    $this->assertTrue($result->ok);
  }

  public function testBlocksUnsafeHtml(): void {
    $inner = $this->createMock(ChannelAdapterInterface::class);
    $inner->expects($this->never())->method('send');
    $decorator = $this->decorator($inner);

    $result = $decorator->send(new Message(
      channel: 'email',
      to: 'desk@example.com',
      subject: 'Reservation confirmed',
      bodyText: 'Itinerary',
      key: 'booking_confirmed_guest',
      bodyHtml: '<p onclick="alert(1)">Hi</p>',
    ));
    $this->assertSame(SendResult::BLOCKED, $result->status);
  }

  public function testAllowsViewportMetaContentAttribute(): void {
    $inner = $this->createMock(ChannelAdapterInterface::class);
    $inner->expects($this->once())
      ->method('send')
      ->willReturn(SendResult::sent());
    $decorator = $this->decorator($inner);

    $result = $decorator->send(new Message(
      channel: 'email',
      to: 'desk@example.com',
      subject: 'New vehicle requirement',
      bodyText: 'Name: Test',
      key: 'contact_inquiry_desk',
      bodyHtml: '<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body><p>Hi</p></body></html>',
    ));
    $this->assertTrue($result->ok);
  }

  private function decorator(ChannelAdapterInterface $inner): SecurityChannelDecorator {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('email')->willReturn([
      'subject_max_length' => 200,
      'body_max_length' => 20000,
    ]);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('lm_notify.settings')->willReturn($config);
    return new SecurityChannelDecorator($inner, new EmailValidator(), $factory);
  }

  private function message(string $to = 'desk@example.com'): Message {
    return new Message(
      channel: 'email',
      to: $to,
      subject: 'New vehicle requirement',
      bodyText: 'Name: Test',
      key: 'contact_inquiry',
      replyTo: 'guest@example.com',
    );
  }

}
