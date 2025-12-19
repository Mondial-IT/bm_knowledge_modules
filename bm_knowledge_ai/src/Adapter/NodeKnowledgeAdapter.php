<?php

declare(strict_types=1);

namespace Drupal\bm_knowledge_ai\Adapter;

use Drupal\bm_knowledge_ai\Model\KnowledgeItem;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;

/**
 * Adapter exposing nodes as knowledge items.
 */
class NodeKnowledgeAdapter implements KnowledgeAdapterInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected LanguageManagerInterface $languageManager,
    protected AccountProxyInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function discover(): iterable {
    $config = $this->configFactory->get('bm_knowledge_ai.node_adapter');
    if (!$config?->get('enabled')) {
      return [];
    }

    $bundles = array_filter((array) $config->get('bundles'));
    if (empty($bundles)) {
      return [];
    }

    $storage = $this->getNodeStorage();
    $query = $storage->getQuery()
      ->condition('status', 1)
      ->condition('type', $bundles, 'IN')
      ->accessCheck(TRUE);

    $nids = $query->execute();
    if (empty($nids)) {
      return [];
    }

    $nodes = $storage->loadMultiple($nids);
    foreach ($nodes as $node) {
      if ($node instanceof NodeInterface && $node->access('view', $this->currentUser)) {
        $translations = $this->collectTranslations($node, (string) $config->get('language_behavior'));
        foreach ($translations as $translation) {
          yield $translation;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function normalize(mixed $raw): KnowledgeItem {
    if (!$raw instanceof NodeInterface) {
      throw new \InvalidArgumentException('NodeKnowledgeAdapter expects NodeInterface instances.');
    }

    $config = $this->configFactory->get('bm_knowledge_ai.node_adapter');
    $fields = (array) ($config?->get('fields') ?? []);
    $body_field = (string) ($fields['body'] ?? 'body');

    $language = $raw->language()?->getId() ?: $this->languageManager->getDefaultLanguage()->getId();
    $body_markdown = $this->normalizeBody($raw, $body_field);

    $item = new KnowledgeItem(
      id: sprintf('node:%d:%s', $raw->id(), $language),
      sourceType: $this->getSourceType(),
      sourceId: sprintf('node:%d', $raw->id()),
      title: $raw->label() ?? '',
      bodyMarkdown: $body_markdown,
      summary: NULL,
      language: $language,
      taxonomyTerms: [],
      primaryTerm: NULL,
      contextHints: [
        'route' => 'entity.node.canonical',
        'bundle' => $raw->bundle(),
        'module' => 'node',
      ],
      authorityLevel: (string) ($config?->get('authority_level') ?? 'canonical'),
      updatedAt: $raw->getChangedTime() ?: NULL,
      extra: [
        'source' => 'node',
        'module' => 'node',
        'description' => $body_markdown,
        'help_topic_type' => 'node',
      ],
    );

    return $item;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceType(): string {
    return 'node';
  }

  /**
   * Collects translations to process based on language behavior.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Node translations.
   */
  protected function collectTranslations(NodeInterface $node, string $language_behavior): array {
    if ($language_behavior !== 'per_translation') {
      return [$node];
    }

    $translations = [];
    foreach ($node->getTranslationLanguages() as $langcode => $language) {
      if ($node->hasTranslation($langcode)) {
        $translations[] = $node->getTranslation($langcode);
      }
    }

    return $translations ?: [$node];
  }

  /**
   * Returns the node storage.
   */
  protected function getNodeStorage(): EntityStorageInterface {
    return $this->entityTypeManager->getStorage('node');
  }

  /**
   * Extracts and normalizes a body field as markdown-compatible plain text.
   */
  protected function normalizeBody(NodeInterface $node, string $field_name): string {
    $value = '';
    if ($node->hasField($field_name) && !$node->get($field_name)->isEmpty()) {
      $value = (string) $node->get($field_name)->first()->value;
    }

    $stripped = trim(strip_tags($value));
    return Html::decodeEntities($stripped);
  }

}
