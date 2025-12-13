<?php

declare(strict_types=1);

namespace Drupal\bm_help_ai\Service;

/**
 * Placeholder AI relevance service.
 *
 * Accepts a set of help items and context, and returns them unchanged for now.
 */
class HelpAiRelevanceService {

  /**
   * Reorder help items based on context (stub).
   *
   * @param array<int, array<string, mixed>> $items
   *   Help items to evaluate.
   * @param array<string, mixed> $context
   *   Contextual data from the request.
   *
   * @return array<int, array<string, mixed>>
   *   Reordered help items.
   */
  public function reorder(array $items, array $context): array {
    // TODO: Integrate AI-assisted ordering or filtering while keeping
    // deterministic fallbacks.
    return $items;
  }

}
