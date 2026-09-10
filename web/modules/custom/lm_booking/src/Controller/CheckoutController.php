<?php

declare(strict_types=1);

namespace Drupal\lm_booking\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;

/**
 * Checkout route title and vehicle access.
 *
 * The route parameter is {vehicle}, not {node}, so node route context
 * and vehicle Block Layout do not treat checkout as a vehicle page.
 */
final class CheckoutController {

  public function title(NodeInterface $vehicle): TranslatableMarkup {
    return new TranslatableMarkup('Checkout — @title', ['@title' => $vehicle->label()]);
  }

  public function access(NodeInterface $vehicle, AccountInterface $account): AccessResultInterface {
    $allowed = $vehicle->bundle() === 'vehicle' && $vehicle->isPublished() && $vehicle->access('view', $account);
    return AccessResult::allowedIf($allowed)->addCacheableDependency($vehicle);
  }

}
