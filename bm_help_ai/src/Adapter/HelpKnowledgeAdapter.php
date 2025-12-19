<?php

declare(strict_types=1);

namespace Drupal\bm_help_ai\Adapter;

use Drupal\bm_knowledge_ai\Adapter\KnowledgeAdapterInterface;
use Drupal\bm_knowledge_ai\Model\KnowledgeItem;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\help\HelpTopicPluginManagerInterface;
use Stringable;

/**
 * Adapter exposing Drupal help topics and hook_help to the knowledge layer.
 */
class HelpKnowledgeAdapter implements KnowledgeAdapterInterface {
  use StringTranslationTrait;

  public function __construct(
    protected HelpTopicPluginManagerInterface $helpTopicManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected RouteMatchInterface $routeMatch,
    protected RendererInterface $renderer,
    protected RouteProviderInterface $routeProvider,
    protected LanguageManagerInterface $languageManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function discover(): iterable {
    foreach ($this->helpTopicManager->getDefinitions() as $id => $definition) {
      yield [
        'type' => 'help_topic',
        'id' => $id,
        'definition' => $definition,
      ];
    }

    foreach ($this->moduleHandler->getModuleList() as $module => $extension) {
      $route_name = 'help.page.' . $module;
      $help = $this->moduleHandler->invoke($module, 'help', [$route_name, $this->routeMatch]);
      if ($help === null || $help === []) {
        continue;
      }

      yield [
        'type' => 'hook_help',
        'id' => 'hook_help.' . $module,
        'module' => $module,
        'extension' => $extension,
        'help' => $help,
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function normalize(mixed $raw): KnowledgeItem {
    if (is_array($raw) && ($raw['type'] ?? '') === 'hook_help') {
      return $this->normalizeModuleHelp($raw);
    }

    return $this->normalizeHelpTopic(is_array($raw) ? $raw : []);
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceType(): string {
    return 'help';
  }

  /**
   * Normalizes a help topic definition to a KnowledgeItem.
   *
   * @param array<string, mixed> $raw
   *   Help topic definition.
   */
  protected function normalizeHelpTopic(array $raw): KnowledgeItem {
    $id = (string) ($raw['id'] ?? '');
    $definition = $raw['definition'] ?? [];
    $title = $this->ensureTitle(is_array($definition) ? $definition : [], $id);

    $language = $this->languageManager->getCurrentLanguage()?->getId() ?? 'und';
    $description = $this->renderHelpTopicSummary($id);

    $item = new KnowledgeItem(
      id: $id,
      sourceType: $this->getSourceType(),
      sourceId: $id,
      title: $title,
      bodyMarkdown: $description,
      summary: $description,
      language: $language,
      taxonomyTerms: [],
      primaryTerm: NULL,
      contextHints: [],
      authorityLevel: 'canonical',
      updatedAt: NULL,
      extra: [
        'source' => 'help_topic',
        'module' => (string) ($definition['provider'] ?? 'unknown'),
        'description' => $description,
        'tags' => [],
        'link' => Url::fromRoute('help.help_topic', ['id' => $id]),
        'is_overview' => (bool) ($definition['bm_help_ai_overview'] ?? FALSE),
        'help_topic_type' => 'help_topic',
        'help_topic_status' => 'current',
      ],
    );

    return $item;
  }

  /**
   * Normalizes a hook_help entry to a KnowledgeItem.
   *
   * @param array<string, mixed> $raw
   *   Hook help data.
   */
  protected function normalizeModuleHelp(array $raw): KnowledgeItem {
    $module = (string) ($raw['module'] ?? '');
    $extension = $raw['extension'] instanceof Extension ? $raw['extension'] : null;
    $route_name = 'help.page.' . $module;
    $language = $this->languageManager->getCurrentLanguage()?->getId() ?? 'und';
    $description = $this->normalizeDescription($raw['help'] ?? '');

    $item = new KnowledgeItem(
      id: (string) ($raw['id'] ?? ('hook_help.' . $module)),
      sourceType: $this->getSourceType(),
      sourceId: 'hook_help.' . $module,
      title: $this->getModuleLabel($extension, $module),
      bodyMarkdown: $description,
      summary: $description,
      language: $language,
      taxonomyTerms: [],
      primaryTerm: NULL,
      contextHints: [],
      authorityLevel: 'canonical',
      updatedAt: NULL,
      extra: [
        'source' => 'hook_help',
        'module' => $module,
        'description' => $description,
        'tags' => [],
        'help_topic_type' => '.module',
        'link' => $this->buildHelpLink($route_name, $module),
        'link_title' => $this->t('@name module help', ['@name' => $this->getModuleLabel($extension, $module)]),
      ],
    );

    return $item;
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
  protected function getModuleLabel(?Extension $extension, string $fallback): string {
    $info = $extension?->info ?? [];

    return (string) ($info['name'] ?? $fallback);
  }

  /**
   * Ensures a title string is available for a help topic.
   */
  protected function ensureTitle(array $definition, string $id): string {
    $title = (string) ($definition['label'] ?? ($definition['title'] ?? ''));
    if ($title === '') {
      return $id;
    }
    return $title;
  }

  /**
   * Checks if a route exists before generating a link.
   */
  protected function routeExists(string $route_name): bool {
    try {
      $this->routeProvider->getRouteByName($route_name);
      return TRUE;
    }
    catch (\Exception) {
      return FALSE;
    }
  }

  /**
   * Builds a link to a module help page, with a safe fallback.
   */
  protected function buildHelpLink(string $route_name, string $module): ?Url {
    if ($this->routeExists($route_name)) {
      return Url::fromRoute($route_name);
    }

    // Fallback to the known path pattern.
    return Url::fromUri('base:/admin/help/' . $module);
  }

}
