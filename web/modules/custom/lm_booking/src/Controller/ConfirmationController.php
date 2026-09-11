<?php

declare(strict_types=1);

namespace Drupal\lm_booking\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\lm_booking\ReservationCode;
use Drupal\lm_booking\ReservationPresenter;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Post-checkout itinerary. Parameter is {booking}, not {node}.
 */
final class ConfirmationController implements ContainerInjectionInterface {

  public function __construct(
    private readonly ReservationPresenter $presenter,
    private readonly ReservationCode $reservationCode,
    private readonly RequestStack $requestStack,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('lm_booking.reservation_presenter'),
      $container->get('lm_booking.reservation_code'),
      $container->get('request_stack'),
    );
  }

  /**
   * @return array<string, mixed>
   */
  public function view(NodeInterface $booking): array {
    $view = $this->presenter->build($booking);

    return [
      '#theme' => 'lm_booking_confirmation',
      '#reference' => $view['reference'],
      '#guest' => $view['guest'],
      '#trip' => $view['trip'],
      '#quote' => $view['quote'],
      '#fleet_url' => $view['fleet_url'],
      '#manage_url' => $view['manage_url'],
      '#attached' => ['library' => ['luxury_motors/checkout']],
      '#cache' => [
        'max-age' => 0,
        'contexts' => ['url.query_args', 'url.path'],
        'tags' => $booking->getCacheTags(),
      ],
    ];
  }

  public function access(NodeInterface $booking, AccountInterface $account): AccessResultInterface {
    $confirmed = $booking->bundle() === 'booking'
      && $booking->isPublished()
      && $booking->hasField('field_booking_status')
      && $booking->get('field_booking_status')->value === 'confirmed';
    $result = AccessResult::allowedIf($confirmed)->addCacheableDependency($booking);
    $code = $this->reservationCode->fromBooking($booking);
    if ($code === '') {
      return $result;
    }
    $digest = $this->requestStack->getCurrentRequest()?->query->get(ReservationCode::QUERY_KEY);
    $digest = is_scalar($digest) ? (string) $digest : '';
    return $result->andIf(AccessResult::allowedIf($this->reservationCode->matches($code, $digest)))
      ->addCacheContexts(['url.query_args:' . ReservationCode::QUERY_KEY]);
  }

}
