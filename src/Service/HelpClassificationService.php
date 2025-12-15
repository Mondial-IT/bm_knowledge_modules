<?php

declare(strict_types=1);

namespace Drupal\bm_help_ai\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Manages help classification against taxonomy terms.
 *
 * Classification data is stored in config `bm_help_ai.classification` as an
 * associative array keyed by help item ID. Term trees are built from the
 * `bm_help_ai_help_topics` vocabulary.
 */
class HelpClassificationService {

  private const CONFIG_NAME = 'bm_help_ai.classification';
  private const VOCABULARY_ID = 'bm_help_ai_help_topics';
  private const METADATA_VOCABULARY_ID = 'help_topics_metadata';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Attaches classification metadata to normalized help items.
   *
   * @param array<int, array<string, mixed>> $items
   *   Normalized help items.
   *
   * @return array<int, array<string, mixed>>
   *   Items with classification fields `terms`, `primary_term`, `manual_order`.
   */
  public function attachClassification(array $items): array {
    $mapping = $this->getClassificationMap();

    foreach ($items as &$item) {
      $item_id = (string) ($item['id'] ?? '');
      $item['terms'] = $mapping[$item_id]['terms'] ?? [];
      $item['primary_term'] = $mapping[$item_id]['primary_term'] ?? null;
      $item['manual_order'] = $mapping[$item_id]['manual_order'] ?? null;
      $item['help_topic_type'] = $mapping[$item_id]['help_topic_type'] ?? null;
      $item['help_topic_status'] = $mapping[$item_id]['help_topic_status'] ?? null;
    }

    return $items;
  }

  /**
   * Filters items to those tagged with a term or its descendants.
   *
   * @param array<int, array<string, mixed>> $items
   *   Items with attached `terms`.
   * @param int|null $tid
   *   Selected term id. If null or not found, returns input.
   *
   * @return array<int, array<string, mixed>>
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

    $filtered = array_filter($items, static function (array $item) use ($valid_term_ids): bool {
      $item_terms = $item['terms'] ?? [];
      return (bool) array_intersect($valid_term_ids, $item_terms);
    });

    return array_values($filtered);
  }

  /**
   * Builds a nested term tree for sidebar navigation.
   *
   * @return array<int, array<string, mixed>>
   *   Tree items with keys: tid, name, depth, url, count, children.
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
   * Retrieves term IDs for a term and all descendants.
   *
   * @param int $tid
   *   Parent term id.
   *
   * @return int[]
   *   Term ids including the parent.
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
   * Counts classified items per term.
   *
   * @return array<int, int>
   *   Map of term id to item count.
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
   * Returns classification map from configuration.
   *
   * @return array<string, array<string, mixed>>
   *   Map keyed by help id.
   */
  protected function getClassificationMap(): array {
    $config = $this->configFactory->get(self::CONFIG_NAME);
    $map = (array) ($config?->get('items') ?? []);
    $metadata = $this->loadMetadataMap();

    foreach ($metadata as $id => $meta) {
      $map[$id] = array_merge($map[$id] ?? [], $meta);
    }

    return $map;
  }

  /**
   * Builds metadata map from the metadata vocabulary.
   *
   * @return array<string, array<string, mixed>>
   *   Map of help id to metadata.
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
        'help_topic_type' => $term->get('field_help_topic_type')->value ?? null,
        'help_topic_status' => $term->get('field_help_topic_status')->value ?? null,
      ];
    }

    return $map;
  }

}
