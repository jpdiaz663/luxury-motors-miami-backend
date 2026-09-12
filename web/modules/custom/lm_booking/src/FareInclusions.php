<?php

declare(strict_types=1);

namespace Drupal\lm_booking;

/**
 * Mock rate inclusions until tariff rules are implemented.
 *
 * @phpstan-type InclusionItem array{label: string, detail: string}
 */
final class FareInclusions {

  /**
   * @return list<InclusionItem>
   */
  public function items(): array {
    return [
      [
        'label' => (string) t('Seguro total del vehículo'),
        'detail' => (string) t('Robo y accidente (CDW/LDW)'),
      ],
      [
        'label' => (string) t('Seguro a terceros'),
        'detail' => (string) t('SLP / LIS / TPL / ALI'),
      ],
      [
        'label' => (string) t('Asistencia al vehículo'),
        'detail' => (string) t('Falla mecánica'),
      ],
      [
        'label' => (string) t('Un conductor adicional'),
        'detail' => '',
      ],
      [
        'label' => (string) t('Kilometraje ilimitado'),
        'detail' => '',
      ],
      [
        'label' => (string) t('Todos los impuestos y tasas locales'),
        'detail' => '',
      ],
    ];
  }

  /**
   * @return list<string>
   */
  public function notes(): array {
    return [
      (string) t('Reserve ahora sin tarjeta de crédito y pague solo en el mostrador.'),
      (string) t('Cancelación gratis: sin tasa de cancelación.'),
    ];
  }

}
