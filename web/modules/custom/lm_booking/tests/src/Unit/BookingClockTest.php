<?php

declare(strict_types=1);

namespace Drupal\Tests\lm_booking\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\lm_booking\BookingClock;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @covers \Drupal\lm_booking\BookingClock
 */
final class BookingClockTest extends UnitTestCase {

  public function testUsesSiteTimezoneForPickupAndNow(): void {
    $clock = $this->clock('America/New_York', '2026-09-15 10:30:00');
    $this->assertSame('America/New_York', $clock->timezone()->getName());
    $this->assertSame('2026-09-15 10:30:00', $clock->now()->format('Y-m-d H:i:s'));

    $pickup = $clock->pickupFromBooking($this->bookingNode('2026-09-15', '10:00'));
    $this->assertInstanceOf(\DateTimeImmutable::class, $pickup);
    $this->assertSame('America/New_York', $pickup->getTimezone()->getName());
    $this->assertSame('2026-09-15 10:00:00', $pickup->format('Y-m-d H:i:s'));
  }

  public function testStartWindowIsTwoHoursFromPickup(): void {
    $clock = $this->clock('America/New_York', '2026-09-15 10:00:00');
    $pickup = new \DateTimeImmutable('2026-09-15 10:00:00', new \DateTimeZone('America/New_York'));
    $this->assertTrue($clock->inStartWindow($pickup));
    $this->assertFalse($clock->graceExpired($pickup));

    $clock = $this->clock('America/New_York', '2026-09-15 11:59:00');
    $this->assertTrue($clock->inStartWindow($pickup));

    $clock = $this->clock('America/New_York', '2026-09-15 12:00:00');
    $this->assertFalse($clock->inStartWindow($pickup));
    $this->assertTrue($clock->graceExpired($pickup));
  }

  public function testLookbackIsTwentyFourHours(): void {
    $pickup = new \DateTimeImmutable('2026-09-14 10:00:00', new \DateTimeZone('America/New_York'));
    $inside = $this->clock('America/New_York', '2026-09-15 09:00:00');
    $this->assertTrue($inside->withinLookback($pickup));
    $outside = $this->clock('America/New_York', '2026-09-15 11:00:00');
    $this->assertFalse($outside->withinLookback($pickup));
  }

  private function clock(string $tz, string $localNow): BookingClock {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('timezone.default')->willReturn($tz);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('system.date')->willReturn($config);
    $time = $this->createMock(TimeInterface::class);
    $stamp = (new \DateTimeImmutable($localNow, new \DateTimeZone($tz)))->getTimestamp();
    $time->method('getRequestTime')->willReturn($stamp);
    return new BookingClock($factory, $time);
  }

  private function bookingNode(string $date, string $hour): NodeInterface {
    $booking = $this->createMock(NodeInterface::class);
    $booking->method('hasField')->willReturn(TRUE);
    $booking->method('get')->willReturnCallback(function (string $field) use ($date, $hour) {
      $value = match ($field) {
        'field_booking_start' => $date,
        'field_booking_pickup_time' => $hour,
        default => NULL,
      };
      $list = $this->createMock(FieldItemListInterface::class);
      $list->method('isEmpty')->willReturn($value === NULL);
      $list->method('getString')->willReturn((string) $value);
      return $list;
    });
    return $booking;
  }

}
