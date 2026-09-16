<?php

declare(strict_types=1);

namespace Drupal\Tests\lm_booking\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\lm_booking\BookingClock;
use Drupal\lm_booking\Event\BookingGraceExpiredEvent;
use Drupal\lm_booking\GracePeriodProcessor;
use Drupal\lm_booking\ReservationGuestMailerInterface;
use Drupal\lm_notify\SendResult;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \Drupal\lm_booking\GracePeriodProcessor
 */
final class GracePeriodProcessorTest extends UnitTestCase {

  public function testSendsStartMailOnceWhileInGraceWindow(): void {
    $state = $this->baseState();
    $booking = $this->booking($state);
    $mailer = $this->createMock(ReservationGuestMailerInterface::class);
    $mailer->expects($this->once())->method('sendGuest')->willReturn(SendResult::sent());
    $processor = $this->processor($mailer, '2026-09-15 10:30:00');

    $processor->processBooking($booking);
    $this->assertNotEmpty($state['field_booking_grace_notified']);
    $this->assertSame('confirmed', $state['field_booking_status']);

    $processor->processBooking($booking);
  }

  public function testRetriesStartMailWhenSendFails(): void {
    $state = $this->baseState();
    $booking = $this->booking($state);
    $mailer = $this->createMock(ReservationGuestMailerInterface::class);
    $mailer->expects($this->exactly(2))->method('sendGuest')->willReturn(SendResult::failed('smtp'));
    $processor = $this->processor($mailer, '2026-09-15 10:30:00');

    $processor->processBooking($booking);
    $this->assertNull($state['field_booking_grace_notified']);
    $processor->processBooking($booking);
  }

  public function testDoesNotCancelPickedUpBooking(): void {
    $state = $this->baseState();
    $state['field_booking_status'] = 'in_progress';
    $booking = $this->booking($state);
    $mailer = $this->createMock(ReservationGuestMailerInterface::class);
    $mailer->expects($this->never())->method('sendGuest');
    $processor = $this->processor($mailer, '2026-09-15 13:00:00');

    $processor->processBooking($booking);
    $this->assertSame('in_progress', $state['field_booking_status']);
  }

  public function testCancelsAfterGraceEvenIfCancelMailFailsThenRetriesMail(): void {
    $state = $this->baseState();
    $booking = $this->booking($state);
    $mailer = $this->createMock(ReservationGuestMailerInterface::class);
    $mailer->expects($this->exactly(2))->method('sendGuest')->willReturnOnConsecutiveCalls(
      SendResult::failed('smtp'),
      SendResult::sent(),
    );
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects($this->once())->method('dispatch')->with($this->isInstanceOf(BookingGraceExpiredEvent::class));
    $processor = $this->processor($mailer, '2026-09-15 12:15:00', $dispatcher);

    $processor->processBooking($booking);
    $this->assertSame('cancelled', $state['field_booking_status']);
    $this->assertNull($state['field_booking_grace_cancel_sent']);

    $processor->processBooking($booking);
    $this->assertSame('cancelled', $state['field_booking_status']);
    $this->assertNotEmpty($state['field_booking_grace_cancel_sent']);
  }

  /**
   * @param array<string, mixed> $state
   */
  private function processor(
    ReservationGuestMailerInterface $mailer,
    string $localNow,
    ?EventDispatcherInterface $dispatcher = NULL,
  ): GracePeriodProcessor {
    $processor = new GracePeriodProcessor(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->clock($localNow),
      $mailer,
      $dispatcher ?? $this->createMock(EventDispatcherInterface::class),
      $this->createMock(LoggerInterface::class),
    );
    $processor->setStringTranslation($this->getStringTranslationStub());
    return $processor;
  }

  private function clock(string $localNow): BookingClock {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('timezone.default')->willReturn('America/New_York');
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('system.date')->willReturn($config);
    $time = $this->createMock(TimeInterface::class);
    $stamp = (new \DateTimeImmutable($localNow, new \DateTimeZone('America/New_York')))->getTimestamp();
    $time->method('getRequestTime')->willReturn($stamp);
    return new BookingClock($factory, $time);
  }

  /**
   * @return array<string, mixed>
   */
  private function baseState(): array {
    return [
      'field_booking_status' => 'confirmed',
      'field_booking_start' => '2026-09-15',
      'field_booking_pickup_time' => '10:00',
      'field_booking_code' => 'LM-TEST1',
      'field_booking_grace_notified' => NULL,
      'field_booking_grace_cancel_sent' => NULL,
    ];
  }

  /**
   * @param array<string, mixed> $state
   */
  private function booking(array &$state): NodeInterface {
    $booking = $this->createMock(NodeInterface::class);
    $booking->method('id')->willReturn(42);
    $booking->method('hasField')->willReturn(TRUE);
    $booking->method('get')->willReturnCallback(function (string $field) use (&$state) {
      $list = $this->createMock(FieldItemListInterface::class);
      $value = $state[$field] ?? NULL;
      $list->method('isEmpty')->willReturn($value === NULL || $value === '');
      $list->method('getString')->willReturn((string) $value);
      return $list;
    });
    $booking->method('set')->willReturnCallback(function (string $field, mixed $value) use (&$state, $booking) {
      $state[$field] = $value;
      return $booking;
    });
    $booking->method('save')->willReturn(42);
    return $booking;
  }

}
