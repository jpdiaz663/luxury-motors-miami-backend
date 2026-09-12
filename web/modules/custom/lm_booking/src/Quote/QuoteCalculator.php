<?php

declare(strict_types=1);

namespace Drupal\lm_booking\Quote;

/**
 * Builds a checkout quote. Fee rates are temporary fixtures, not Twig.
 *
 * Tax base: rental + disinfection. Location recovery: rental only.
 */
final class QuoteCalculator {

  private const DISINFECTION_FEE = 10.00;
  private const SALES_TAX_RATE = 0.07;
  private const FL_SURCHARGE_PER_DAY = 2.00;
  private const LOCATION_RECOVERY_RATE = 0.095;

  public function days(string $pickup, string $return): int {
    $start = \DateTimeImmutable::createFromFormat('Y-m-d', $pickup);
    $end = \DateTimeImmutable::createFromFormat('Y-m-d', $return);
    if (!$start || !$end) {
      return 1;
    }
    $days = (int) $start->diff($end)->format('%r%a');
    return max(1, $days);
  }

  public function quote(
    float $daily_price,
    int $days,
    string $pickup_location,
    string $dropoff_location,
  ): Quote {
    $days = max(1, $days);
    $rental = round($daily_price * $days, 2);
    $pickup_fee = 0.0;
    $dropoff_fee = 0.0;
    $disinfection = self::DISINFECTION_FEE;
    $tax = round(($rental + $disinfection) * self::SALES_TAX_RATE, 2);
    $fl = round(self::FL_SURCHARGE_PER_DAY * $days, 2);
    $recovery = round($rental * self::LOCATION_RECOVERY_RATE, 2);

    $lines = [
      new QuoteLine(
        'booking',
        'rental',
        (string) t('Booking for @days days', ['@days' => $days]),
        (string) t('USD @daily × day', ['@daily' => number_format($daily_price, 2, '.', '')]),
        $rental,
      ),
      new QuoteLine(
        'location',
        'pickup',
        (string) t('Pick up in @place', ['@place' => $pickup_location]),
        '',
        $pickup_fee,
      ),
      new QuoteLine(
        'location',
        'dropoff',
        (string) t('Drop off in @place', ['@place' => $dropoff_location]),
        '',
        $dropoff_fee,
      ),
      new QuoteLine(
        'fee',
        'disinfection',
        (string) t('(1) Desinfecting Fee'),
        (string) t('USD @amount for Booking', ['@amount' => number_format($disinfection, 2, '.', '')]),
        $disinfection,
      ),
      new QuoteLine(
        'tax',
        'sales_tax',
        (string) t('Sales Tax (@rate%)', ['@rate' => '7.00']),
        '',
        $tax,
      ),
      new QuoteLine(
        'surcharge',
        'fl_surcharge',
        (string) t('FL State Surcharge'),
        (string) t('USD @amount × day', ['@amount' => number_format(self::FL_SURCHARGE_PER_DAY, 2, '.', '')]),
        $fl,
      ),
      new QuoteLine(
        'surcharge',
        'location_recovery',
        (string) t('Location Recovery Fee (@rate%)', ['@rate' => '9.50']),
        '',
        $recovery,
      ),
    ];

    $total = round($rental + $pickup_fee + $dropoff_fee + $disinfection + $tax + $fl + $recovery, 2);

    return new Quote($days, $daily_price, $rental, $total, $lines);
  }

  public static function formatMoney(float $amount): string {
    return '$' . number_format($amount, 2);
  }

}
