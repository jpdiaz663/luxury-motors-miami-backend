<?php

declare(strict_types=1);

namespace Drupal\Tests\lm_notify\Unit;

use Drupal\Component\Utility\EmailValidator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Site\Settings;
use Drupal\lm_notify\Adapter\HostingerPhpMailerAdapter;
use Drupal\lm_notify\Message;
use Drupal\lm_notify\SendResult;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Drupal\lm_notify\Adapter\HostingerPhpMailerAdapter
 */
final class HostingerPhpMailerAdapterTest extends UnitTestCase {

  public function testSkipsWhenSmtpCredentialsMissing(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('email')->willReturn([
      'transport' => 'hostinger',
      'host' => 'smtp.hostinger.com',
      'port' => 465,
      'encryption' => 'ssl',
      'from_address' => 'noreply@example.com',
      'from_name' => 'Luxury Motors',
      'timeout' => 15,
    ]);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('lm_notify.settings')->willReturn($config);

    $adapter = new HostingerPhpMailerAdapter(
      $factory,
      new Settings(['lm_notify' => ['smtp_username' => '', 'smtp_password' => '']]),
      new EmailValidator(),
      $this->createMock(LoggerInterface::class),
    );

    $result = $adapter->send(new Message(
      channel: 'email',
      to: 'desk@example.com',
      subject: 'New vehicle requirement',
      bodyText: 'Name: Test',
      key: 'contact_inquiry',
    ));

    $this->assertSame(SendResult::SKIPPED_UNCONFIGURED, $result->status);
    $this->assertFalse($result->ok);
  }

  public function testPhpMailFailsClosedWhenFromAddressMissing(): void {
    $notify = $this->createMock(ImmutableConfig::class);
    $notify->method('get')->with('email')->willReturn([
      'transport' => 'php_mail',
      'from_address' => '',
    ]);
    $site = $this->createMock(ImmutableConfig::class);
    $site->method('get')->with('mail')->willReturn('');
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->willReturnMap([
      ['lm_notify.settings', $notify],
      ['system.site', $site],
    ]);

    $adapter = new HostingerPhpMailerAdapter(
      $factory,
      new Settings(['lm_notify' => []]),
      new EmailValidator(),
      $this->createMock(LoggerInterface::class),
    );

    $result = $adapter->send(new Message(
      channel: 'email',
      to: 'desk@example.com',
      subject: 'New vehicle requirement',
      bodyText: 'Name: Test',
      key: 'contact_inquiry',
    ));

    $this->assertSame(SendResult::FAILED, $result->status);
    $this->assertSame('php_mail', $result->provider);
  }

}
