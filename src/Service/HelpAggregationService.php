<?php

declare(strict_types=1);

namespace Drupal\bm_help_ai\Service;

use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\help\HelpTopicPluginManagerInterface;
use Stringable;

/**
 * Collects and normalizes help data from Drupal core systems.
 */
class HelpAggregationService {

  public function __construct(
    protected HelpTopicPluginManagerInterface $helpTopicManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected RouteMatchInterface $routeMatch,
    protected RendererInterface $renderer,
    protected HelpClassificationService $classificationService,
  ) {}

  /**
   * Returns normalized help topic data.
   *
   * @return array<int, array<string, mixed>>
   *   Help topic data ready for rendering.
   */
  public function getHelpTopics(): array {
    $items = [];

    foreach ($this->helpTopicManager->getDefinitions() as $id => $definition) {
      $items[] = [
        'id' => $id,
        'title' => (string) ($definition['label'] ?? $id),
        'source' => 'help_topic',
        'module' => (string) ($definition['provider'] ?? 'unknown'),
        'description' => $this->renderHelpTopicSummary($id),
        'tags' => [],
        'link' => Url::fromRoute('help.help_topic', ['id' => $id]),
        'is_overview' => (bool) ($definition['bm_help_ai_overview'] ?? FALSE),
        'help_topic_type' => 'help_topic',
        'help_topic_status' => 'current',
      ];
    }

    return $this->classificationService->attachClassification($items);
  }

  /**
   * Returns normalized data from hook_help().
   *
   * @return array<int, array<string, mixed>>
   *   Module overview help entries.
   */
  public function getModuleHelp(): array {
    $items = [];

    foreach ($this->moduleHandler->getModuleList() as $module => $extension) {
      $help = $this->moduleHandler->invoke($module, 'help', ['help.page.' . $module, $this->routeMatch]);
      if ($help === null || $help === []) {
        continue;
      }

      $items[] = [
        'id' => 'hook_help.' . $module,
        'title' => $this->getModuleLabel($extension, $module),
        'source' => 'hook_help',
        'module' => $module,
        'description' => $this->normalizeDescription($help),
        'tags' => [],
        'help_topic_type' => '.module',
      ];
    }

    return $this->classificationService->attachClassification($items);
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
   * Renders a concise summary for a help topic.
   */
  protected function renderHelpTopicSummary(string $id): string {
    try {
      $help_topic = $this->helpTopicManager->createInstance($id);
      $body = $help_topic->getBody();
      $rendered = $this->renderer->renderPlain($body);

      return $this->trimDescription($rendered);
    }
    catch (\Throwable $throwable) {
      return '';
    }
  }

  /**
   * Normalizes description data to a trimmed plain-text string.
   */
  protected function normalizeDescription(string|array|Stringable|null $description): string {
    if (is_array($description)) {
      $description = $this->renderer->renderPlain($description);
    }

    if ($description instanceof Stringable) {
      $description = (string) $description;
    }

    return $this->trimDescription((string) $description);
  }

  /**
   * Limits descriptions to a readable length.
   */
  protected function trimDescription(string $description): string {
    $clean = trim(strip_tags($description));
    if (strlen($clean) <= 280) {
      return $clean;
    }

    return substr($clean, 0, 277) . '...';
  }

  /**
   * Resolves a human-readable module label.
   */
  protected function getModuleLabel(Extension $extension, string $fallback): string {
    $info = $extension->info ?? [];

    return (string) ($info['name'] ?? $fallback);
  }

}
