<?php

declare(strict_types=1);

namespace Drupal\lm_notify\Adapter;

use Drupal\lm_notify\Message;
use Drupal\lm_notify\SendResult;

/**
 * Transport for one notification channel (email, SMS, …).
 */
interface ChannelAdapterInterface {

  public function send(Message $message): SendResult;

}
