<?php

declare(strict_types=1);

namespace Drupal\bm_knowledge_ai\Adapter;

use Drupal\bm_knowledge_ai\Model\KnowledgeItem;

/**
 * Contract for adapters that expose knowledge items.
 */
interface KnowledgeAdapterInterface {

  /**
   * Discover raw source records that can be normalized to knowledge items.
   *
   * @return iterable<mixed>
   *   Raw items from the underlying source.
   */
  public function discover(): iterable;

  /**
   * Converts a raw source record to a KnowledgeItem.
   *
   * @param mixed $raw
   *   Raw data returned from discover().
   */
  public function normalize(mixed $raw): KnowledgeItem;

  /**
   * Returns a stable source type (e.g. help, node, document).
   */
  public function getSourceType(): string;

}
