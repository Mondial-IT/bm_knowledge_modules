<?php

declare(strict_types=1);

namespace Drupal\bm_knowledge_ai\Controller;

use Drupal\bm_knowledge_ai\Service\KnowledgeAdapterManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Simple demo screen to showcase knowledge items available to AI.
 */
class KnowledgeDemoController extends ControllerBase {

  public function __construct(
    protected KnowledgeAdapterManager $adapterManager,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('bm_knowledge_ai.adapter_manager'),
    );
  }

  /**
   * Renders a demo overview of knowledge items.
   */
  public function demo(): array {
    $items = $this->adapterManager->getItems();

    $counts = [];
    foreach ($items as $item) {
      $source_type = $item->sourceType ?? 'unknown';
      $counts[$source_type] = ($counts[$source_type] ?? 0) + 1;
    }

    $rows = [];
    $sample = array_slice($items, 0, 25);
    foreach ($sample as $item) {
      $rows[] = [
        'data' => [
          ['data' => ['#plain_text' => $item->id]],
          ['data' => ['#plain_text' => $item->title]],
          ['data' => ['#plain_text' => $item->sourceType]],
          ['data' => ['#plain_text' => $item->language]],
          ['data' => ['#plain_text' => $item->authorityLevel ?? '']],
        ],
      ];
    }

    $build = [
      '#title' => $this->t('Knowledge AI demo'),
      'intro' => [
        '#markup' => $this->t('This page lists knowledge items exposed through bm_knowledge_ai adapters. It is intended to demonstrate what is available for AI consumers without adding any new UI behavior.'),
      ],
      'counts' => [
        '#theme' => 'item_list',
        '#title' => $this->t('Item counts by source type'),
        '#items' => array_map(function ($type) use ($counts) {
          return $this->t('@type: @count items', ['@type' => $type, '@count' => $counts[$type]]);
        }, array_keys($counts)),
      ],
      'table' => [
        '#type' => 'table',
        '#caption' => $this->t('Sample knowledge items (first @count)', ['@count' => count($rows)]),
        '#header' => [
          $this->t('ID'),
          $this->t('Title'),
          $this->t('Source type'),
          $this->t('Language'),
          $this->t('Authority'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('No knowledge items available.'),
      ],
    ];

    $link = Link::fromTextAndUrl(
      $this->t('Configure adapters'),
      Url::fromRoute('bm_knowledge_ai.settings')
    )->toRenderable();

    $build['configure'] = [
      '#type' => 'container',
      'link' => $link,
    ];

    return $build;
  }

}
