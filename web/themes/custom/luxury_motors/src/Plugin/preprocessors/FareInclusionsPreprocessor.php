<?php

declare(strict_types=1);

namespace Drupal\luxury_motors\Plugin\preprocessors;

use Drupal\block_content\BlockContentInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\preprocessors\PreprocessorPluginBase;

/**
 * Prepares kicker, heading, items, and notes for the fare_inclusions block type.
 */
final class FareInclusionsPreprocessor extends PreprocessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array &$variables, string $hook, array $info): void {
    $block = $this->blockContent($variables);

    if (!$block instanceof BlockContentInterface || $block->bundle() !== 'fare_inclusions') {
      return;
    }

    $variables['kicker'] = $this->stringField($block, 'field_kicker');
    $variables['heading'] = $this->stringField($block, 'field_headline');
    $variables['items'] = $this->items($block);
    $variables['notes'] = $this->notes($block);
    $variables['#attached']['library'][] = 'luxury_motors/fare_inclusions';
  }

  /**
   * @param array<string, mixed> $variables
   */
  private function blockContent(array $variables): ?BlockContentInterface {
    $candidates = [
      $variables['content']['#block_content'] ?? NULL,
      $variables['elements']['content']['#block_content'] ?? NULL,
    ];
    foreach ($candidates as $candidate) {
      if ($candidate instanceof BlockContentInterface) {
        return $candidate;
      }
    }
    return NULL;
  }

  /**
   * @return list<array{label: string, detail: string}>
   */
  private function items(BlockContentInterface $block): array {
    if (!$block->hasField('field_fare_items') || $block->get('field_fare_items')->isEmpty()) {
      return [];
    }

    $items = [];
    foreach ($block->get('field_fare_items')->referencedEntities() as $paragraph) {
      if (!$paragraph instanceof ParagraphInterface || $paragraph->bundle() !== 'fare_item') {
        continue;
      }

      $label = $this->stringField($paragraph, 'field_headline');
      $detail = $this->stringField($paragraph, 'field_description');
      if ($label === '' && $detail === '') {
        continue;
      }

      $items[] = [
        'label' => $label,
        'detail' => $detail,
      ];
    }

    return $items;
  }

  /**
   * @return list<string>
   */
  private function notes(BlockContentInterface $block): array {
    if (!$block->hasField('field_notes') || $block->get('field_notes')->isEmpty()) {
      return [];
    }

    $notes = [];
    foreach ($block->get('field_notes') as $item) {
      $value = trim((string) ($item->value ?? ''));
      if ($value !== '') {
        $notes[] = $value;
      }
    }

    return $notes;
  }

  private function stringField(BlockContentInterface|ParagraphInterface $entity, string $field_name): string {
    if (!$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
      return '';
    }
    return trim((string) ($entity->get($field_name)->value ?? ''));
  }

}
