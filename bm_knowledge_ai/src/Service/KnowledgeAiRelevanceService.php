<?php

declare(strict_types=1);

namespace Drupal\bm_knowledge_ai\Service;

use Drupal\bm_knowledge_ai\Model\KnowledgeItem;

/**
 * Placeholder relevance service operating on knowledge items.
 */
class KnowledgeAiRelevanceService {

  /**
   * Reorders knowledge items based on context (stub).
   *
   * @param array<int, \Drupal\bm_knowledge_ai\Model\KnowledgeItem|array<string, mixed>> $items
   *   Knowledge items to evaluate.
   * @param array<string, mixed> $context
   *   Contextual data from the request.
   *
   * @return array<int, \Drupal\bm_knowledge_ai\Model\KnowledgeItem|array<string, mixed>>
   *   Reordered items (unchanged for now).
   */
  public function reorder(array $items, array $context): array {
    // TODO: Integrate AI-assisted ordering or filtering while keeping
    // deterministic fallbacks.
    return $items;
  }

}
