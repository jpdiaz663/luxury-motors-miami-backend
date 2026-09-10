<?php

declare(strict_types=1);

namespace Drupal\luxury_motors\Plugin\preprocessors;

use Drupal\block_content\BlockContentInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\preprocessors\PreprocessorPluginBase;

/**
 * Prepares terms cards and FAQ items for the terms_and_faqs block type.
 */
final class TermsAndFaqsPreprocessor extends PreprocessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array &$variables, string $hook, array $info): void {
    $block = $this->blockContent($variables);

    if (!$block instanceof BlockContentInterface || $block->bundle() !== 'terms_and_faqs') {
      return;
    }

    $variables['headline'] = $this->stringField($block, 'field_headline');
    $variables['term_cards'] = $this->termCards($block);
    $variables['faqs'] = $this->faqs($block);
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
   * @return list<array{title: string, items: list<string>}>
   */
  private function termCards(BlockContentInterface $block): array {
    if (!$block->hasField('field_term_cards') || $block->get('field_term_cards')->isEmpty()) {
      return [];
    }

    $cards = [];
    foreach ($block->get('field_term_cards')->referencedEntities() as $paragraph) {
      if (!$paragraph instanceof ParagraphInterface || $paragraph->bundle() !== 'term_card') {
        continue;
      }

      $items = [];
      if ($paragraph->hasField('field_term_items') && !$paragraph->get('field_term_items')->isEmpty()) {
        foreach ($paragraph->get('field_term_items') as $item) {
          $value = trim((string) ($item->value ?? ''));
          if ($value !== '') {
            $items[] = $value;
          }
        }
      }

      $title = $this->stringField($paragraph, 'field_headline');
      if ($title === '' && $items === []) {
        continue;
      }

      $cards[] = [
        'title' => $title,
        'items' => $items,
      ];
    }

    return $cards;
  }

  /**
   * @return list<array{question: string, answer: string}>
   */
  private function faqs(BlockContentInterface $block): array {
    if (!$block->hasField('field_faqs') || $block->get('field_faqs')->isEmpty()) {
      return [];
    }

    $faqs = [];
    foreach ($block->get('field_faqs')->referencedEntities() as $paragraph) {
      if (!$paragraph instanceof ParagraphInterface || $paragraph->bundle() !== 'faq_item') {
        continue;
      }

      $question = $this->stringField($paragraph, 'field_headline');
      $answer = $this->stringField($paragraph, 'field_description');
      if ($question === '' && $answer === '') {
        continue;
      }

      $faqs[] = [
        'question' => $question,
        'answer' => $answer,
      ];
    }

    return $faqs;
  }

  private function stringField(BlockContentInterface|ParagraphInterface $entity, string $field_name): string {
    if (!$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
      return '';
    }
    return trim((string) ($entity->get($field_name)->value ?? ''));
  }

}
