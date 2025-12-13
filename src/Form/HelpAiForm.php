<?php

declare(strict_types=1);

namespace Drupal\bm_help_ai\Form;

use Drupal\bm_help_ai\Service\HelpAggregationService;
use Drupal\bm_help_ai\Service\HelpAiRelevanceService;
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
  ) {}

  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('bm_help_ai.help_aggregation'),
      $container->get('bm_help_ai.help_context'),
      $container->get('bm_help_ai.help_ai_relevance')
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
    $topics = $this->aggregationService->getHelpTopics();
    $module_overviews = $this->aggregationService->getModuleOverviews();
    $situational_candidates = $this->aggregationService->getSituationCandidates($context);
    $situational_help = $this->relevanceService->reorder($situational_candidates, $context);

    $form['intro'] = [
      '#markup' => $this->t('Aggregates Drupal help topics, module overviews, and context-aware suggestions. AI integration points are stubbed and clearly separated.'),
    ];

    $form['topics'] = [
      '#type' => 'details',
      '#title' => $this->t('Topics'),
      '#open' => TRUE,
      'items' => [
        '#theme' => 'item_list',
        '#items' => $this->buildHelpList($topics),
      ],
    ];

    $form['module_overviews'] = [
      '#type' => 'details',
      '#title' => $this->t('Module overviews'),
      '#open' => TRUE,
      'items' => [
        '#theme' => 'item_list',
        '#items' => $this->buildHelpList($module_overviews),
      ],
    ];

    $form['situational'] = [
      '#type' => 'details',
      '#title' => $this->t('Situation-specific help'),
      '#description' => $this->t('Ordered using contextual signals such as route, roles, and enabled modules.'),
      '#open' => TRUE,
      'items' => [
        '#theme' => 'item_list',
        '#items' => $this->buildHelpList($situational_help),
      ],
    ];

    $form['ai_assistance'] = [
      '#type' => 'details',
      '#title' => $this->t('AI assistance (placeholder)'),
      '#open' => TRUE,
      'content' => [
        '#type' => 'textarea',
        '#title' => $this->t('AI-generated summary'),
        '#default_value' => $this->t('AI-assisted help will appear here once configured. Canonical help content remains authoritative and is listed above.'),
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
          '#plain_text' => $this->t('Source: @source | Module: @module', [
            '@source' => $item['source'] ?? 'unknown',
            '@module' => $item['module'] ?? 'n/a',
          ]),
        ],
      ];
    }

    return $list;
  }

  /**
   * Builds a renderable title element, with linking when available.
   */
  protected function buildItemTitle(array $item): array {
    $link = $item['link'] ?? null;
    if ($link instanceof Url) {
      return Link::fromTextAndUrl($item['title'], $link)->toRenderable();
    }

    return [
      '#plain_text' => $item['title'] ?? '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
