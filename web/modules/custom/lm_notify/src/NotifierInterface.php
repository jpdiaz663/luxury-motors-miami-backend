<?php

declare(strict_types=1);

namespace Drupal\lm_notify;

/**
 * Application facade for sending notifications.
 */
interface NotifierInterface {

  public function send(Message $message): SendResult;

}
