<?php

declare(strict_types=1);

namespace Drupal\Tests\lm_vehicle\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\lm_vehicle\FleetCatalog;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @covers \Drupal\lm_vehicle\FleetCatalog
 */
final class FleetCatalogTest extends UnitTestCase {

  public function testPickupBeforeLeadTimeIsRejected(): void {
    $catalog = $this->catalog('America/New_York', '2026-09-15 10:00:00');
    $this->assertSame('lead', $catalog->tripIssue('2026-09-16', '09:00', '2026-09-20', '10:00'));
    $this->assertFalse($catalog->validTrip('2026-09-16', '09:00', '2026-09-20', '10:00'));
  }

  public function testPickupAtEarliestHourIsAccepted(): void {
    $catalog = $this->catalog('America/New_York', '2026-09-15 10:00:00');
    $this->assertSame('2026-09-16 10:00:00', $catalog->earliestPickupInstant()->format('Y-m-d H:i:s'));
    $this->assertNull($catalog->tripIssue('2026-09-16', '10:00', '2026-09-20', '10:00'));
    $this->assertTrue($catalog->validTrip('2026-09-16', '10:00', '2026-09-20', '10:00'));
  }

  public function testLeadTimeRoundsUpToNextHour(): void {
    $catalog = $this->catalog('America/New_York', '2026-09-15 10:17:00');
    $this->assertSame('2026-09-16 11:00:00', $catalog->earliestPickupInstant()->format('Y-m-d H:i:s'));
    $this->assertSame('lead', $catalog->tripIssue('2026-09-16', '10:00', '2026-09-20', '10:00'));
    $this->assertTrue($catalog->validTrip('2026-09-16', '11:00', '2026-09-20', '11:00'));
  }

  public function testReturnBeforePickupIsRejected(): void {
    $catalog = $this->catalog('America/New_York', '2026-09-15 10:00:00');
    $this->assertSame('window', $catalog->tripIssue('2026-09-20', '10:00', '2026-09-18', '10:00'));
  }

  public function testSameDayReturnAfterPickupIsAccepted(): void {
    $catalog = $this->catalog('America/New_York', '2026-09-15 10:00:00');
    $this->assertNull($catalog->tripIssue('2026-09-16', '10:00', '2026-09-16', '14:00'));
  }

  public function testSameDayReturnBeforePickupIsRejected(): void {
    $catalog = $this->catalog('America/New_York', '2026-09-15 10:00:00');
    $this->assertSame('return', $catalog->tripIssue('2026-09-16', '14:00', '2026-09-16', '10:00'));
  }

  public function testMinRentalHoursCanRequireNextDay(): void {
    $catalog = $this->catalog('America/New_York', '2026-09-15 10:00:00');
    $this->assertSame('return', $catalog->tripIssue('2026-09-16', '10:00', '2026-09-16', '14:00', 24));
    $this->assertNull($catalog->tripIssue('2026-09-16', '10:00', '2026-09-17', '10:00', 24));
  }

  public function testDefaultWindowStartsAtEarliestPickup(): void {
    $catalog = $this->catalog('America/New_York', '2026-09-15 21:30:00');
    $window = $catalog->defaultWindow();
    $this->assertSame('2026-09-16', $window['pickup']);
    $this->assertSame('22:00', $window['ptime']);
    $this->assertSame('2026-09-19', $window['return']);
    $this->assertSame('22:00', $window['rtime']);
  }

  public function testValidWindowStillIgnoresClock(): void {
    $catalog = $this->catalog('America/New_York', '2026-09-15 10:00:00');
    $this->assertTrue($catalog->validWindow('2026-09-15', '2026-09-16'));
    $this->assertFalse($catalog->validWindow('2026-09-16', '2026-09-15'));
  }

  private function catalog(string $tz, string $localNow): FleetCatalog {
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $request = $this->createMock(RequestStack::class);
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('timezone.default')->willReturn($tz);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('system.date')->willReturn($config);
    $time = $this->createMock(TimeInterface::class);
    $stamp = (new \DateTimeImmutable($localNow, new \DateTimeZone($tz)))->getTimestamp();
    $time->method('getRequestTime')->willReturn($stamp);

    return new FleetCatalog($entities, $request, $factory, $time);
  }

}
