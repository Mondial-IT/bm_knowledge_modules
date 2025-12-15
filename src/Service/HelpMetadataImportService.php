<?php

declare(strict_types=1);

namespace Drupal\bm_help_ai\Service;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\VocabularyInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\help\HelpTopicPluginManagerInterface;

/**
 * Imports help topic metadata into a taxonomy vocabulary.
 */
class HelpMetadataImportService {

  public const METADATA_VOCABULARY = 'help_topics_metadata';
  private const F_TIMESTAMP = 'field_help_topic_timestamp';
  private const F_TYPE = 'field_help_topic_type';
  private const F_LABEL = 'field_help_topic_label';
  private const F_TOP_LEVEL = 'field_help_topic_top_level';
  private const F_RELATED = 'field_help_topic_related';
  private const F_STATUS = 'field_help_topic_status';

  public function __construct(
    protected HelpTopicPluginManagerInterface $helpTopicManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected ExtensionPathResolver $extensionPathResolver,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Imports metadata for help topics and module hook_help entries.
   *
   * @return array<string, mixed>
   *   Summary counts.
   */
  public function import(): array {
    $this->ensureVocabularyAndFields();

    $imported = 0;
    $updated = 0;
    $not_found = 0;

    foreach ($this->helpTopicManager->getDefinitions() as $id => $definition) {
      $metadata = $this->buildHelpTopicMetadata($id, $definition);
      $status = $this->persistMetadataTerm($metadata);
      $imported++;
      if ($status === 'updated') {
        $updated++;
      }
      if ($status === 'not_found') {
        $not_found++;
      }
    }

    foreach ($this->moduleHandler->getModuleList() as $name => $extension) {
      $metadata = $this->buildModuleMetadata($name);
      $status = $this->persistMetadataTerm($metadata);
      $imported++;
      if ($status === 'updated') {
        $updated++;
      }
    }

    return [
      'imported' => $imported,
      'updated' => $updated,
      'not_found' => $not_found,
    ];
  }

  /**
   * Builds metadata for a help topic plugin.
   */
  protected function buildHelpTopicMetadata(string $id, array $definition): array {
    $label = (string) ($definition['label'] ?? $id);
    $path = $this->resolveHelpTopicPath($definition, $id);
    $front_matter = $path ? $this->extractFrontMatter($path) : [];
    $timestamp = $path && file_exists($path) ? filemtime($path) : null;

    return [
      'id' => $id,
      'label' => $front_matter['label'] ?? $label,
      'type' => 'help_topic',
      'top_level' => (bool) ($front_matter['top_level'] ?? FALSE),
      'related' => $front_matter['related'] ?? [],
      'timestamp' => $timestamp,
    ];
  }

  /**
   * Builds metadata for a module .module help entry.
   */
  protected function buildModuleMetadata(string $module): array {
    $extension = $this->moduleHandler->getModuleList()[$module];
    $module_path = $this->extensionPathResolver->getPath('module', $module);
    $module_file = $module_path . '/' . $module . '.module';
    $timestamp = file_exists($module_file) ? filemtime($module_file) : null;

    return [
      'id' => 'hook_help.' . $module,
      'label' => $extension->info['name'] ?? $module,
      'type' => '.module',
      'top_level' => FALSE,
      'related' => [],
      'timestamp' => $timestamp,
    ];
  }

  /**
   * Ensures vocabulary and required fields exist.
   */
  protected function ensureVocabularyAndFields(): void {
    $vocabulary = Vocabulary::load(self::METADATA_VOCABULARY);
    if (!$vocabulary instanceof VocabularyInterface) {
      $vocabulary = Vocabulary::create([
        'vid' => self::METADATA_VOCABULARY,
        'name' => 'Help topics metadata',
      ]);
      $vocabulary->save();
    }

    $this->ensureFieldStorage(self::F_TIMESTAMP, 'datetime', [
      'datetime_type' => 'datetime',
    ]);
    $this->ensureFieldStorage(self::F_TYPE, 'list_string', [
      'allowed_values' => [
        'help_topic' => 'help_topic',
        '.module' => '.module',
        'wiki' => 'wiki',
      ],
    ]);
    $this->ensureFieldStorage(self::F_LABEL, 'string');
    $this->ensureFieldStorage(self::F_TOP_LEVEL, 'boolean');
    $this->ensureFieldStorage(self::F_RELATED, 'string_long');
    $this->ensureFieldStorage(self::F_STATUS, 'list_string', [
      'allowed_values' => [
        'current' => 'current',
        'not_found' => 'not_found',
        'updated' => 'updated',
      ],
    ]);

    $this->ensureFieldConfig(self::F_TIMESTAMP, $vocabulary);
    $this->ensureFieldConfig(self::F_TYPE, $vocabulary);
    $this->ensureFieldConfig(self::F_LABEL, $vocabulary);
    $this->ensureFieldConfig(self::F_TOP_LEVEL, $vocabulary);
    $this->ensureFieldConfig(self::F_RELATED, $vocabulary);
    $this->ensureFieldConfig(self::F_STATUS, $vocabulary);
  }

  /**
   * Creates field storage if missing.
   */
  protected function ensureFieldStorage(string $field_name, string $type, array $settings = []): void {
    $storage = FieldStorageConfig::loadByName('taxonomy_term', $field_name);
    if ($storage) {
      return;
    }

    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'taxonomy_term',
      'type' => $type,
      'settings' => $settings,
      'cardinality' => 1,
    ])->save();
  }

  /**
   * Creates field config on vocabulary if missing.
   */
  protected function ensureFieldConfig(string $field_name, VocabularyInterface $vocabulary): void {
    $field = FieldConfig::loadByName('taxonomy_term', $vocabulary->id(), $field_name);
    if ($field) {
      return;
    }

    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'taxonomy_term',
      'bundle' => $vocabulary->id(),
      'label' => $field_name,
    ])->save();
  }

  /**
   * Saves metadata into taxonomy term and returns resulting status.
   */
  protected function persistMetadataTerm(array $metadata): string {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $existing = $storage->loadByProperties([
      'vid' => self::METADATA_VOCABULARY,
      'name' => $metadata['id'],
    ]);
    /** @var \Drupal\taxonomy\Entity\Term|null $term */
    $term = $existing ? reset($existing) : null;
    $previous_timestamp = null;

    if (!$term instanceof Term) {
      $term = Term::create([
        'vid' => self::METADATA_VOCABULARY,
        'name' => $metadata['id'],
      ]);
    }
    else {
      $previous_timestamp = $term->get(self::F_TIMESTAMP)?->value ?? null;
    }

    $status = $this->determineStatus($previous_timestamp, $metadata['timestamp']);

    $term->set(self::F_LABEL, $metadata['label'] ?? $metadata['id']);
    $term->set(self::F_TYPE, $metadata['type'] ?? 'help_topic');
    $term->set(self::F_TOP_LEVEL, (bool) ($metadata['top_level'] ?? FALSE));
    $term->set(self::F_RELATED, $this->normalizeRelated($metadata['related'] ?? []));
    $term->set(self::F_STATUS, $status);

    if (!empty($metadata['timestamp'])) {
      $term->set(self::F_TIMESTAMP, gmdate('Y-m-d\TH:i:s', (int) $metadata['timestamp']));
    }

    $term->save();

    return $status;
  }

  /**
   * Determines status based on timestamp comparison.
   */
  protected function determineStatus(?string $stored_timestamp, ?int $file_mtime): string {
    if (empty($file_mtime)) {
      return 'not_found';
    }
    if (empty($stored_timestamp)) {
      return 'current';
    }

    $stored = strtotime($stored_timestamp);
    return $file_mtime > $stored ? 'updated' : 'current';
  }

  /**
   * Extracts front matter YAML block from a Twig file.
   */
  protected function extractFrontMatter(string $file_path): array {
    $contents = @file_get_contents($file_path);
    if ($contents === FALSE) {
      return [];
    }

    if (preg_match('/^---\s*(.*?)\s*---/s', $contents, $matches) !== 1) {
      return [];
    }

    $parsed = Yaml::decode($matches[1]);
    return is_array($parsed) ? $parsed : [];
  }

  /**
   * Attempts to resolve a Twig file path for a help topic definition.
   */
  protected function resolveHelpTopicPath(array $definition, string $id): ?string {
    $provider = $definition['provider'] ?? null;
    if (!$provider) {
      return null;
    }
    $base_path = $this->extensionPathResolver->getPath('module', $provider);
    $relative_path = $definition['path'] ?? 'help_topics';
    $candidate_names = [
      $id,
      str_replace('.', '-', $id),
    ];

    foreach ($candidate_names as $name) {
      if (!$name) {
        continue;
      }
      $candidate = $base_path . '/' . $relative_path . '/' . $name . '.html.twig';
      if (file_exists($candidate)) {
        return $candidate;
      }
    }

    return null;
  }

  /**
   * Normalizes related value to a string.
   *
   * @param array|string $related
   *   Related data from front matter.
   */
  protected function normalizeRelated(array|string $related): string {
    if (is_array($related)) {
      return implode(', ', $related);
    }

    return (string) $related;
  }

}
