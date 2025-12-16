<?php

declare(strict_types=1);

namespace Drupal\bm_help_ai\Form;

use Drupal\bm_help_ai\Service\HelpAggregationService;
use Drupal\bm_help_ai\Service\HelpAiRelevanceService;
use Drupal\bm_help_ai\Service\HelpClassificationService;
use Drupal\bm_help_ai\Service\HelpContextService;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Admin form that aggregates help sources and exposes AI placeholders.
 */
class HelpAiForm extends FormBase {

  public function __construct(
    protected HelpAggregationService $aggregationService,
    protected HelpContextService $contextService,
    protected HelpAiRelevanceService $relevanceService,
    protected HelpClassificationService $classificationService,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('bm_help_ai.help_aggregation'),
      $container->get('bm_help_ai.help_context'),
      $container->get('bm_help_ai.help_ai_relevance'),
      $container->get('bm_help_ai.help_classification'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bm_help_ai_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $context = $this->contextService->getContext();
    $selected_tid = $context['selected_term_id'] ?? null;
    $topics = $this->aggregationService->getHelpTopics();
    $module_overviews = $this->aggregationService->getModuleOverviews();
    $situational_candidates = $this->aggregationService->getSituationCandidates($context);
    $situational_help = $this->relevanceService->reorder($situational_candidates, $context);
    $topics = $this->classificationService->filterByTerm($topics, $selected_tid);
    $module_overviews = $this->classificationService->filterByTerm($module_overviews, $selected_tid);
    $situational_help = $this->classificationService->filterByTerm($situational_help, $selected_tid);
    $tree = $this->classificationService->getTermTree();

    $form['#cache']['contexts'][] = 'url.query_args:tid';
    $form['#attached']['library'][] = 'bm_help_ai/ai_admin';

    $form['layout'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['bm-help-ai-layout']],
    ];

    $form['layout']['sidebar'] = [
      '#type' => 'details',
      '#title' => $this->t('Browse by hierarchy'),
      '#open' => TRUE,
    ];

    if (!empty($tree)) {
      $form['layout']['sidebar']['tree'] = $this->buildTermTreeList($tree, $selected_tid);
    }
    else {
      $form['layout']['sidebar']['empty'] = [
        '#markup' => $this->t('No taxonomy configured; showing all help.'),
      ];
    }

    if ($selected_tid) {
      $form['layout']['sidebar']['filter_state'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['bm-help-ai-filter-state']],
        'status' => ['#markup' => $this->t('Filtering by term ID: @tid', ['@tid' => $selected_tid])],
        'reset' => Link::fromTextAndUrl($this->t('Reset filter'), Url::fromRoute('bm_help_ai.help_ai'))->toRenderable(),
      ];
    }

    $form['layout']['content'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['bm-help-ai-content']],
    ];

    $form['layout']['content']['intro'] = [
      '#markup' => $this->t('Aggregates Drupal help topics, module overviews, and context-aware suggestions. AI integration points are stubbed and clearly separated. Use the hierarchy to narrow results.'),
    ];

    $form['layout']['content']['topics'] = [
      '#type' => 'details',
      '#title' => $this->t('Topics'),
      '#open' => TRUE,
      '#attributes' => ['class' => ['bm-help-ai-table-wrapper']],
      'table' => [
        '#type' => 'table',
        '#attributes' => [
          'class' => ['bm-help-ai-table', 'bm-help-ai-table-topics'],
          'data-bm-help-ai-table' => 'topics',
          'data-has-actions' => 'true',
        ],
        '#header' => [
          $this->t('Title'),
          $this->t('Description'),
          $this->t('Source'),
          $this->t('Module'),
          $this->t('Type'),
          $this->t('Status'),
          $this->t('File'),
          $this->t('Actions'),
        ],
        '#rows' => $this->buildTableRows($topics, TRUE),
        '#empty' => $this->t('No topics available.'),
      ],
    ];

    $form['layout']['content']['module_overviews'] = [
      '#type' => 'details',
      '#title' => $this->t('Module overviews'),
      '#open' => TRUE,
      '#attributes' => ['class' => ['bm-help-ai-table-wrapper']],
      'table' => [
        '#type' => 'table',
        '#attributes' => [
          'class' => ['bm-help-ai-table', 'bm-help-ai-table-overviews'],
          'data-bm-help-ai-table' => 'module-overviews',
          'data-has-actions' => 'true',
        ],
        '#header' => [
          $this->t('Title'),
          $this->t('Description'),
          $this->t('Source'),
          $this->t('Module'),
          $this->t('Type'),
          $this->t('Status'),
          $this->t('File'),
          $this->t('Actions'),
        ],
        '#rows' => $this->buildTableRows($module_overviews, TRUE),
        '#empty' => $this->t('No module overviews available.'),
      ],
    ];

    $form['layout']['content']['situational'] = [
      '#type' => 'details',
      '#title' => $this->t('Situation-specific help'),
      '#description' => $this->t('Ordered using contextual signals such as route, roles, and enabled modules.'),
      '#open' => TRUE,
      'items' => [
        '#theme' => 'item_list',
        '#items' => $this->buildHelpList($situational_help),
      ],
    ];

    $form['layout']['content']['ai_assistance'] = [
      '#type' => 'details',
      '#title' => $this->t('AI assistance (placeholder)'),
      '#open' => TRUE,
      'content' => [
        '#type' => 'textarea',
        '#title' => $this->t('AI-generated summary'),
        '#default_value' => $this->t('AI-assisted help will appear here once configured. Canonical help content remains authoritative and is listed above. Taxonomy filters apply to sections above.'),
        '#attributes' => [
          'readonly' => 'readonly',
          'class' => ['bm-help-ai-placeholder'],
        ],
      ],
    ];

    return $form;
  }

  /**
   * Build renderable list items from normalized help entries.
   *
   * @param array<int, array<string, mixed>> $items
   *   Normalized help items.
   *
   * @return array<int, array<string, mixed>>
   *   Renderable list items.
   */
  protected function buildHelpList(array $items): array {
    $list = [];
    foreach ($items as $item) {
      $list[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['bm-help-ai-item']],
        'title' => $this->buildItemTitle($item),
        'description' => !empty($item['description'])
          ? [
            '#type' => 'container',
            '#attributes' => ['class' => ['bm-help-ai-description']],
            '#plain_text' => $item['description'],
          ]
          : [],
        'meta' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['bm-help-ai-meta']],
          '#plain_text' => $this->formatMeta($item),
        ],
      ];
    }

    return $list;
  }

  /**
   * Builds a renderable title element, with linking when available.
   */
  protected function buildItemTitle(array $item, ?int $term_id = NULL): array {
    $link = $item['link'] ?? null;
    $term_id = $term_id !== null ? (int) $term_id : null;
    if ($link instanceof Url) {
      return [
        '#type' => 'link',
        '#title' => $item['title'] ?? '',
        '#url' => $link,
        '#attributes' => $term_id ? [
          'data-tid' => $term_id,
          'data-dialog-type' => 'dialog',
          'data-dialog-renderer' => 'off_canvas',
          'data-dialog-options' => json_encode(['position' => 'end', 'width' => '40%']),
          'class' => ['use-ajax'],
        ] : [],
      ];
    }

    return [
      '#plain_text' => $item['title'] ?? '',
      '#attributes' => $term_id ? ['data-tid' => $term_id] : [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * Builds table rows for the topics table.
   *
   * @param array<int, array<string, mixed>> $items
   *   Topics to render.
   *
   * @return array<int, array<int, array<string, mixed>>>
   *   Table rows.
   */
  protected function buildTableRows(array $items, bool $with_actions = FALSE): array {
    $rows = [];
    foreach ($items as $item) {
      $term_id = isset($item['terms'][0]) ? (int) $item['terms'][0] : NULL;
      $row = [
        'data' => [
          [
            'data' => $this->buildItemTitle($item, $term_id),
          ],
          [
            'data' => [
              '#plain_text' => !empty($item['description']) ? $item['description'] : (string) $this->t('—'),
            ],
          ],
          [
            'data' => [
              '#plain_text' => (string) ($item['source'] ?? ''),
            ],
          ],
          [
            'data' => [
              '#plain_text' => (string) ($item['module'] ?? ''),
            ],
          ],
          [
            'data' => [
              '#plain_text' => (string) ($item['help_topic_type'] ?? ''),
            ],
          ],
          [
            'data' => [
              '#plain_text' => (string) ($item['help_topic_status'] ?? ''),
            ],
          ],
          [
            'data' => [
              '#plain_text' => (string) ($item['help_topic_path'] ?? ''),
            ],
          ],
        ],
        'class' => ['bm-help-ai-row'],
        'attributes' => $this->buildRowAttributes($item),
      ];
      if ($with_actions) {
        $row['data'][] = [
          'data' => $this->buildEditAction($item),
        ];
      }
      $rows[] = $row;
    }
    return $rows;
  }

  /**
   * Builds the edit action link for a row.
   */
  protected function buildEditAction(array $item): array {
    $term_id = $item['terms'][0] ?? NULL;
    if (!$term_id) {
      return ['#markup' => $this->t('—')];
    }

    $url = Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $term_id]);
    return [
      '#type' => 'link',
      '#title' => $this->t('Edit'),
      '#url' => $url,
      '#attributes' => [
        'class' => ['bm-help-ai-edit', 'use-ajax'],
        'data-dialog-type' => 'dialog',
        'data-dialog-renderer' => 'off_canvas',
        'data-dialog-options' => json_encode(['position' => 'end', 'width' => '40%']),
      ],
    ];
  }

  /**
   * Adds useful attributes to table rows.
   */
  protected function buildRowAttributes(array $item): array {
    $attributes = [];
    if (!empty($item['terms'][0])) {
      $attributes['data-term-id'] = $item['terms'][0];
    }
    return $attributes;
  }

  /**
   * Builds nested term list render array.
   *
   * @param array<int, array<string, mixed>> $tree
   *   Tree entries.
   * @param int|null $selected_tid
   *   Currently selected term.
   */
  protected function buildTermTreeList(array $tree, ?int $selected_tid): array {
    $items = [];

    foreach ($tree as $entry) {
      $link = Link::fromTextAndUrl(
        $this->formatTreeItemLabel($entry),
        Url::fromRoute('bm_help_ai.help_ai', [], ['query' => ['tid' => $entry['tid']]])
      )->toRenderable();
      if ($selected_tid === $entry['tid']) {
        $link['#attributes']['class'][] = 'is-active';
      }

      $item = [
        '#type' => 'container',
        '#attributes' => ['class' => ['bm-help-ai-tree-item']],
        'link' => $link,
      ];

      if (!empty($entry['children'])) {
        $item['children'] = $this->buildTermTreeList($entry['children'], $selected_tid);
      }

      $items[] = $item;
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
    ];
  }

  /**
   * Formats meta information string for a help item.
   */
  protected function formatMeta(array $item): string {
    $parts = [];
    $parts[] = $this->t('Source: @source', ['@source' => $item['source'] ?? 'unknown']);
    $parts[] = $this->t('Module: @module', ['@module' => $item['module'] ?? 'n/a']);

    if (!empty($item['help_topic_type'])) {
      $parts[] = $this->t('Type: @type', ['@type' => $item['help_topic_type']]);
    }
    if (!empty($item['help_topic_status'])) {
      $parts[] = $this->t('Status: @status', ['@status' => $item['help_topic_status']]);
    }

    return implode(' | ', $parts);
  }

  /**
   * Formats a tree item label with count.
   */
  protected function formatTreeItemLabel(array $entry): string {
    $count = (int) ($entry['count'] ?? 0);
    return $count > 0
      ? $this->t('@name (@count)', ['@name' => $entry['name'], '@count' => $count])
      : $entry['name'];
  }

}
