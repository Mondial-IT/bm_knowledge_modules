<?php

declare(strict_types=1);

namespace Drupal\bm_knowledge_ai\Service;

use Drupal\bm_knowledge_ai\Adapter\KnowledgeAdapterInterface;
use Drupal\bm_knowledge_ai\Model\KnowledgeItem;

/**
 * Collects knowledge adapters and returns normalized knowledge items.
 */
class KnowledgeAdapterManager {

  /**
   * @param iterable<\Drupal\bm_knowledge_ai\Adapter\KnowledgeAdapterInterface> $adapters
   *   Tagged adapters.
   */
  public function __construct(
    protected iterable $adapters,
    protected KnowledgeClassificationService $classificationService,
  ) {}

  /**
   * Returns knowledge items from all or a specific source type.
   *
   * @param string|null $source_type
   *   Optional source type filter.
   *
   * @return \Drupal\bm_knowledge_ai\Model\KnowledgeItem[]
   *   Normalized knowledge items with classification attached.
   */
  public function getItems(?string $source_type = NULL): array {
    $items = [];

    foreach ($this->adapters as $adapter) {
      if (!$adapter instanceof KnowledgeAdapterInterface) {
        continue;
      }
      if ($source_type !== NULL && $adapter->getSourceType() !== $source_type) {
        continue;
      }
      foreach ($adapter->discover() as $raw) {
        $item = $adapter->normalize($raw);
        if ($item instanceof KnowledgeItem) {
          $items[] = $item;
        }
      }
    }

    return $this->classificationService->attachClassification($items);
  }

}
