<?php

declare(strict_types=1);

namespace Drupal\bm_knowledge_ai\Model;

/**
 * Canonical representation of a knowledge item.
 */
class KnowledgeItem {

  public string $id;
  public string $sourceType;
  public string $sourceId;
  public string $title;
  public string $bodyMarkdown;
  public ?string $summary;
  public string $language;
  public array $taxonomyTerms;
  public ?int $primaryTerm;
  public array $contextHints;
  public ?string $authorityLevel;
  public ?int $updatedAt;
  public array $extra;

  public function __construct(
    string $id,
    string $sourceType,
    string $sourceId,
    string $title,
    string $bodyMarkdown,
    ?string $summary = NULL,
    string $language = 'und',
    array $taxonomyTerms = [],
    ?int $primaryTerm = NULL,
    array $contextHints = [],
    ?string $authorityLevel = NULL,
    ?int $updatedAt = NULL,
    array $extra = [],
  ) {
    $this->id = $id;
    $this->sourceType = $sourceType;
    $this->sourceId = $sourceId;
    $this->title = $title;
    $this->bodyMarkdown = $bodyMarkdown;
    $this->summary = $summary;
    $this->language = $language;
    $this->taxonomyTerms = array_values($taxonomyTerms);
    $this->primaryTerm = $primaryTerm;
    $this->contextHints = $contextHints;
    $this->authorityLevel = $authorityLevel;
    $this->updatedAt = $updatedAt;
    $this->extra = $extra;
  }

  /**
   * Adds or updates metadata on the knowledge item.
   */
  public function addExtra(array $extra): void {
    $this->extra = array_merge($this->extra, $extra);
  }

  /**
   * Sets taxonomy references on the knowledge item.
   *
   * @param int[] $terms
   *   Term ids to attach.
   * @param int|null $primary_term
   *   Optional primary term id.
   */
  public function setTaxonomy(array $terms, ?int $primary_term = NULL): void {
    $this->taxonomyTerms = array_values(array_unique(array_map('intval', $terms)));
    $this->primaryTerm = $primary_term !== NULL ? (int) $primary_term : NULL;
  }

  /**
   * Exports the knowledge item as an associative array.
   *
   * @return array<string, mixed>
   *   Array representation used by consumers.
   */
  public function toArray(): array {
    $data = [
      'id' => $this->id,
      'source_type' => $this->sourceType,
      'source_id' => $this->sourceId,
      'title' => $this->title,
      'body_markdown' => $this->bodyMarkdown,
      'summary' => $this->summary,
      'language' => $this->language,
      'taxonomy_terms' => $this->taxonomyTerms,
      'terms' => $this->taxonomyTerms,
      'primary_term' => $this->primaryTerm,
      'context_hints' => $this->contextHints,
      'authority_level' => $this->authorityLevel,
      'updated_at' => $this->updatedAt,
      // Backwards-compatibility for help consumers.
      'source' => $this->extra['source'] ?? $this->sourceType,
    ];

    return array_merge($data, $this->extra);
  }

}
