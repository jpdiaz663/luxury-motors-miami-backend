<?php

declare(strict_types=1);

namespace Drupal\lm_booking\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\lm_booking\CheckoutSession;
use Drupal\lm_vehicle\FleetCatalog;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Validates Reserve and stores the trip in the Drupal session.
 */
final class CheckoutStartController implements ContainerInjectionInterface {

  public function __construct(
    private readonly CheckoutSession $checkoutSession,
    private readonly FleetCatalog $fleetCatalog,
    private readonly MessengerInterface $messenger,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('lm_booking.checkout_session'),
      $container->get('lm_vehicle.fleet_catalog'),
      $container->get('messenger'),
    );
  }

  public function start(Request $request): JsonResponse|RedirectResponse {
    $input = $this->payload($request);
    $result = $this->checkoutSession->commit($input);
    $checkout = Url::fromRoute('lm_booking.checkout')->toString();
    $json = str_contains((string) $request->headers->get('Accept'), 'json')
      || $request->getContentTypeFormat() === 'json';

    if ($json) {
      return new JsonResponse([
        'ok' => $result['ok'],
        'message' => $result['message'],
        'redirect' => $result['ok'] ? $checkout : '',
      ], $result['ok'] ? 200 : 409, [
        'Cache-Control' => 'no-store, no-cache, must-revalidate',
      ]);
    }

    if (!$result['ok']) {
      $this->messenger->addError($result['message']);
      return new RedirectResponse($this->fleetCatalog->fleetUrl($this->fleetCatalog->currentQuery()), 302, [
        'Cache-Control' => 'no-store, no-cache, must-revalidate',
      ]);
    }

    return new RedirectResponse($checkout, 302, [
      'Cache-Control' => 'no-store, no-cache, must-revalidate',
    ]);
  }

  /**
   * @return array<string, mixed>
   */
  private function payload(Request $request): array {
    $content = $request->getContent();
    if (is_string($content) && $content !== '') {
      $decoded = json_decode($content, TRUE);
      if (is_array($decoded)) {
        return $decoded;
      }
    }
    return $request->request->all();
  }

}
