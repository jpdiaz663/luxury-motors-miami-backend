<?php

declare(strict_types=1);

namespace Drupal\lm_booking\Quote;

/**
 * Booking cost snapshot for checkout presentation.
 */
final class Quote {

  /**
   * @param list<QuoteLine> $lines
   */
  public function __construct(
    public readonly int $days,
    public readonly float $rental_base_daily,
    public readonly float $rental_subtotal,
    public readonly float $total,
    public readonly array $lines,
  ) {}

  public function effectiveDaily(): float {
    return $this->days > 0 ? round($this->total / $this->days, 2) : $this->total;
  }

  /**
   * @return array<string, mixed>
   */
  public function toArray(): array {
    $groups = [];
    foreach ($this->lines as $line) {
      $groups[$line->group][] = $line->toArray();
    }

    return [
      'days' => $this->days,
      'rental_base_daily' => $this->rental_base_daily,
      'rental_base_daily_formatted' => QuoteCalculator::formatMoney($this->rental_base_daily),
      'rental_subtotal' => $this->rental_subtotal,
      'rental_subtotal_formatted' => QuoteCalculator::formatMoney($this->rental_subtotal),
      'total' => $this->total,
      'total_formatted' => QuoteCalculator::formatMoney($this->total),
      'effective_daily' => $this->effectiveDaily(),
      'effective_daily_formatted' => QuoteCalculator::formatMoney($this->effectiveDaily()),
      'lines' => array_map(static fn(QuoteLine $line): array => $line->toArray(), $this->lines),
      'groups' => $groups,
    ];
  }

}
