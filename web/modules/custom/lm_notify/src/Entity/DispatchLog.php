<?php

declare(strict_types=1);

namespace Drupal\lm_notify\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Internal audit record of a notification attempt.
 */
#[ContentEntityType(
  id: 'lm_dispatch_log',
  label: new TranslatableMarkup('Dispatch log'),
  label_collection: new TranslatableMarkup('Dispatch logs'),
  label_singular: new TranslatableMarkup('dispatch log'),
  label_plural: new TranslatableMarkup('dispatch logs'),
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
  handlers: [
    'access' => 'Drupal\Core\Entity\EntityAccessControlHandler',
    'storage' => 'Drupal\Core\Entity\Sql\SqlContentEntityStorage',
  ],
  admin_permission: 'administer lm_notify',
  base_table: 'lm_dispatch_log',
  internal: TRUE,
  translatable: FALSE,
)]
final class DispatchLog extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['channel'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Channel'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 32);

    $fields['provider'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Provider'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 64);

    $fields['message_key'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Message key'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 64);

    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Status'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 32);

    $fields['recipient'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Recipient'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 254);

    $fields['subject'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Subject'))
      ->setSetting('max_length', 255);

    $fields['payload'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Sanitized payload'));

    $fields['error'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Error'));

    $fields['ip_hash'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('IP hash'))
      ->setSetting('max_length', 64);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'));

    return $fields;
  }

}
