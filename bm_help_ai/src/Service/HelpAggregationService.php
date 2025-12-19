<?php

declare(strict_types=1);

namespace Drupal\bm_help_ai\Service;

use Drupal\bm_knowledge_ai\Model\KnowledgeItem;
use Drupal\bm_knowledge_ai\Service\KnowledgeAdapterManager;

/**
 * Provides convenience accessors for help knowledge items.
 */
class HelpAggregationService {

  public function __construct(
    protected KnowledgeAdapterManager $adapterManager,
  ) {}

  /**
   * Returns normalized help topic data.
   *
   * @return array<int, array<string, mixed>>
   *   Help topic data ready for rendering.
   */
  public function getHelpTopics(): array {
    $items = $this->adapterManager->getItems('help');
    $topics = array_filter($items, static function (KnowledgeItem $item): bool {
      $source = $item->extra['source'] ?? '';
      return $source === 'help_topic';
    });

    return array_values(array_map([$this, 'normalizeItem'], $topics));
  }

  /**
   * Returns normalized data from hook_help().
   *
   * @return array<int, array<string, mixed>>
   *   Module overview help entries.
   */
  public function getModuleHelp(): array {
    $items = $this->adapterManager->getItems('help');
    $modules = array_filter($items, static function (KnowledgeItem $item): bool {
      $source = $item->extra['source'] ?? '';
      return $source === 'hook_help';
    });

    return array_values(array_map([$this, 'normalizeItem'], $modules));
  }

  /**
   * Module overview section content.
   *
   * @return array<int, array<string, mixed>>
   *   Hook help data plus topics flagged for overview display.
   */
  public function getModuleOverviews(): array {
    $overviews = $this->getModuleHelp();
    $topics = array_filter($this->getHelpTopics(), static fn(array $item) => !empty($item['is_overview']));

    return array_values(array_merge($overviews, $topics));
  }

  /**
   * Candidates for situation-specific relevance.
   *
   * @param array<string, mixed> $context
   *   Current context data.
   *
   * @return array<int, array<string, mixed>>
   *   Combined help candidates.
   */
  public function getSituationCandidates(array $context): array {
    // Context is unused for now; preserved for future filtering.
    return array_merge($this->getHelpTopics(), $this->getModuleHelp());
  }

  /**
   * Converts a knowledge item to an array.
   */
  protected function normalizeItem(KnowledgeItem $item): array {
    return $item->toArray();
  }

}
