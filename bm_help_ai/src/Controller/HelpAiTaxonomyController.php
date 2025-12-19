<?php

declare(strict_types=1);

namespace Drupal\bm_help_ai\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\taxonomy\TermInterface;

/**
 * Provides a landing page for BM Help AI taxonomy links.
 */
class HelpAiTaxonomyController extends ControllerBase {

  /**
   * Overview page with links to related vocabularies.
   */
  public function overview(): array {
    $items = [
      Link::fromTextAndUrl(
        $this->t('Manage help topics hierarchy'),
        Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => 'bm_help_ai_help_topics'])
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('Manage help topics metadata'),
        Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => 'help_topics_metadata'])
      )->toRenderable(),
    ];

    return [
      '#type' => 'container',
      'description' => [
        '#markup' => $this->t('Access BM Help AI taxonomies for hierarchy and metadata management.'),
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
    ];
  }

  /**
   * Displays a taxonomy term in an admin-themed context for off-canvas dialogs.
   */
  public function view(TermInterface $taxonomy_term): array {
    return $this->entityTypeManager()
      ->getViewBuilder('taxonomy_term')
      ->view($taxonomy_term, 'full');
  }

}
