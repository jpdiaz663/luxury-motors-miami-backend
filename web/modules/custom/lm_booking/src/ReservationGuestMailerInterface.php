<?php

declare(strict_types=1);

namespace Drupal\lm_booking;

use Drupal\node\NodeInterface;
use Drupal\lm_notify\SendResult;

/**
 * Guest-only reservation notices used by scheduled booking jobs.
 */
interface ReservationGuestMailerInterface {

  /**
   * @param array{theme: string, guest_key: string, guest_subject: string, plain_lead: string, grace_ends_when?: string} $notice
   */
  public function sendGuest(NodeInterface $booking, array $notice): SendResult;

}
