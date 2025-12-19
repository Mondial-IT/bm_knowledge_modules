<?php

declare(strict_types=1);

namespace Drupal\bm_knowledge_ai\Service;

use Drupal\bm_knowledge_ai\Model\KnowledgeItem;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Attaches taxonomy metadata to knowledge items.
 */
class KnowledgeClassificationService {

  private const CONFIG_NAME = 'bm_help_ai.classification';
  private const VOCABULARY_ID = 'bm_help_ai_help_topics';
  private const METADATA_VOCABULARY_ID = 'help_topics_metadata';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Attaches taxonomy classification to knowledge items.
   *
   * @param array<int, \Drupal\bm_knowledge_ai\Model\KnowledgeItem|array<string, mixed>> $items
   *   Knowledge items (objects or arrays).
   *
   * @return array<int, \Drupal\bm_knowledge_ai\Model\KnowledgeItem|array<string, mixed>>
   *   Items with taxonomy + metadata attached.
   */
  public function attachClassification(array $items): array {
    $mapping = $this->getClassificationMap();

    foreach ($items as &$item) {
      $item_id = $this->extractId($item);
      if ($item_id === '') {
        continue;
      }

      $entry = $mapping[$item_id] ?? [];
      $terms = $entry['terms'] ?? [];
      $primary_term = $entry['primary_term'] ?? NULL;

      if ($item instanceof KnowledgeItem) {
        $item->setTaxonomy($terms, $primary_term);
        $item->addExtra([
          'manual_order' => $entry['manual_order'] ?? NULL,
          'help_topic_type' => $entry['help_topic_type'] ?? NULL,
          'help_topic_status' => $entry['help_topic_status'] ?? NULL,
          'help_topic_path' => $entry['help_topic_path'] ?? NULL,
        ]);
      }
      elseif (is_array($item)) {
        $item['terms'] = $terms;
        $item['primary_term'] = $primary_term;
        $item['manual_order'] = $entry['manual_order'] ?? NULL;
        $item['help_topic_type'] = $entry['help_topic_type'] ?? NULL;
        $item['help_topic_status'] = $entry['help_topic_status'] ?? NULL;
        $item['help_topic_path'] = $entry['help_topic_path'] ?? NULL;
      }
    }

    return $items;
  }

  /**
   * Filters knowledge items by a taxonomy term (including descendants).
   *
   * @param array<int, \Drupal\bm_knowledge_ai\Model\KnowledgeItem|array<string, mixed>> $items
   *   Items with taxonomy data.
   * @param int|null $tid
   *   Term id filter.
   *
   * @return array<int, \Drupal\bm_knowledge_ai\Model\KnowledgeItem|array<string, mixed>>
   *   Filtered items.
   */
  public function filterByTerm(array $items, ?int $tid): array {
    if (!$tid) {
      return $items;
    }

    $valid_term_ids = $this->getDescendantTermIds($tid);
    if (empty($valid_term_ids)) {
      return $items;
    }

    $filtered = array_filter($items, function (KnowledgeItem|array $item) use ($valid_term_ids): bool {
      $item_terms = $item instanceof KnowledgeItem ? $item->taxonomyTerms : ($item['terms'] ?? []);
      return (bool) array_intersect($valid_term_ids, $item_terms);
    });

    return array_values($filtered);
  }

  /**
   * Builds a nested term tree for navigation.
   *
   * @return array<int, array<string, mixed>>
   *   Tree entries keyed with tid/name/depth/count/children.
   */
  public function getTermTree(): array {
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $tree = $term_storage->loadTree(self::VOCABULARY_ID);
    if (empty($tree)) {
      return [];
    }

    $counts = $this->countItemsPerTerm();
    $lookup = [];
    $roots = [];

    foreach ($tree as $item) {
      $entry = [
        'tid' => (int) $item->tid,
        'name' => $item->name,
        'depth' => (int) $item->depth,
        'count' => $counts[(int) $item->tid] ?? 0,
        'children' => [],
      ];
      $lookup[$entry['tid']] = $entry;
    }

    foreach ($tree as $item) {
      $tid = (int) $item->tid;
      $parent = (int) $item->parents[0] ?? 0;
      if ($parent && isset($lookup[$parent])) {
        $lookup[$parent]['children'][] = &$lookup[$tid];
      }
      else {
        $roots[] = &$lookup[$tid];
      }
    }

    return $roots;
  }

  /**
   * Retrieves a term id and all descendants within the vocabulary.
   */
  public function getDescendantTermIds(int $tid): array {
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $term = $term_storage->load($tid);
    if (!$term instanceof TermInterface || $term->bundle() !== self::VOCABULARY_ID) {
      return [];
    }

    $tree = $term_storage->loadTree(self::VOCABULARY_ID, $tid, NULL, TRUE);
    $ids = [$tid];
    foreach ($tree as $child) {
      $ids[] = (int) $child->id();
    }

    return $ids;
  }

  /**
   * Counts classified items per term using stored mappings.
   *
   * @return array<int, int>
   *   Map of term id to count.
   */
  protected function countItemsPerTerm(): array {
    $counts = [];
    foreach ($this->getClassificationMap() as $entry) {
      foreach ($entry['terms'] ?? [] as $tid) {
        $counts[(int) $tid] = ($counts[(int) $tid] ?? 0) + 1;
      }
    }

    return $counts;
  }

  /**
   * Returns classification map merged with metadata vocabulary data.
   *
   * @return array<string, array<string, mixed>>
   *   Map keyed by knowledge item id.
   */
  protected function getClassificationMap(): array {
    $config = $this->configFactory->get(self::CONFIG_NAME);
    $map = (array) ($config?->get('items') ?? []);
    $metadata = $this->loadMetadataMap();

    foreach ($metadata as $id => $meta) {
      $combined = array_merge($map[$id] ?? [], $meta);
      if (!empty($meta['terms'])) {
        $existing = $combined['terms'] ?? [];
        $combined['terms'] = array_values(array_unique(array_merge($existing, $meta['terms'])));
      }
      $map[$id] = $combined;
    }

    return $map;
  }

  /**
   * Builds metadata map from the knowledge metadata vocabulary.
   *
   * @return array<string, array<string, mixed>>
   *   Map of item id to metadata.
   */
  protected function loadMetadataMap(): array {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $terms = $storage->loadByProperties(['vid' => self::METADATA_VOCABULARY_ID]);
    if (!$terms) {
      return [];
    }

    $map = [];
    foreach ($terms as $term) {
      if (!$term instanceof TermInterface) {
        continue;
      }
      $id = $term->label();
      $map[$id] = [
        'help_topic_type' => $term->get('field_help_topic_type')->value ?? NULL,
        'help_topic_status' => $term->get('field_help_topic_status')->value ?? NULL,
        'help_topic_path' => $term->get('field_help_topic_path')->value ?? NULL,
        'terms' => [$term->id()],
      ];
    }

    return $map;
  }

  /**
   * Extracts the canonical id from a knowledge item or array.
   */
  protected function extractId(KnowledgeItem|array $item): string {
    if ($item instanceof KnowledgeItem) {
      return $item->id;
    }

    return (string) ($item['id'] ?? '');
  }

}
