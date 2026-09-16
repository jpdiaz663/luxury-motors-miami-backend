<?php

declare(strict_types=1);

namespace Drupal\lm_booking\Quote;

/**
 * One priced row. Group keys stay stable so insurance/extras can be inserted later.
 */
final class QuoteLine {

  public function __construct(
    public readonly string $group,
    public readonly string $id,
    public readonly string $label,
    public readonly string $detail,
    public readonly float $amount,
  ) {}

  /**
   * @return array{group: string, id: string, label: string, detail: string, amount: float, amount_formatted: string}
   */
  public function toArray(): array {
    return [
      'group' => $this->group,
      'id' => $this->id,
      'label' => $this->label,
      'detail' => $this->detail,
      'amount' => $this->amount,
      'amount_formatted' => QuoteCalculator::formatMoney($this->amount),
    ];
  }

}
