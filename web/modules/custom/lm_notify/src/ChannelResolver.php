<?php

declare(strict_types=1);

namespace Drupal\lm_notify;

use Drupal\lm_notify\Adapter\ChannelAdapterInterface;

/**
 * Resolves a channel id to a transport adapter.
 *
 * Register additional adapters (SMS, push) by adding them to the constructor map.
 */
final class ChannelResolver {

  /**
   * @param array<string, \Drupal\lm_notify\Adapter\ChannelAdapterInterface> $adapters
   */
  public function __construct(
    private readonly array $adapters,
  ) {}

  public function get(string $channel): ChannelAdapterInterface {
    if (!isset($this->adapters[$channel])) {
      throw new \InvalidArgumentException(sprintf('No notification adapter registered for channel "%s".', $channel));
    }
    return $this->adapters[$channel];
  }

}
