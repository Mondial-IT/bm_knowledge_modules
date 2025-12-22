<?php

declare(strict_types=1);

namespace Drupal\bm_knowledge_ai\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for the NodeKnowledgeAdapter configuration.
 */
class KnowledgeNodeAdapterSettingsForm extends ConfigFormBase {
  use StringTranslationTrait;

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bm_knowledge_ai_node_adapter_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['bm_knowledge_ai.node_adapter'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('bm_knowledge_ai.node_adapter');
    $bundles = $this->getNodeBundles();

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable node adapter'),
      '#default_value' => (bool) $config->get('enabled'),
      '#description' => $this->t('When enabled, published nodes from the selected bundles are exposed as knowledge items.'),
    ];

    $form['bundles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed bundles'),
      '#options' => $bundles,
      '#default_value' => (array) $config->get('bundles'),
      '#description' => $this->t('Only published nodes from these content types will be ingested.'),
    ];

    $form['fields'] = [
      '#type' => 'details',
      '#title' => $this->t('Field mapping'),
      '#open' => TRUE,
    ];
    $form['fields']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title field'),
      '#default_value' => (string) $config->get('fields.title') ?: 'title',
      '#description' => $this->t('Machine name of the field to use for the KnowledgeItem title.'),
      '#required' => TRUE,
    ];
    $form['fields']['body'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Body field'),
      '#default_value' => (string) $config->get('fields.body') ?: 'body',
      '#description' => $this->t('Machine name of the field to use for body text normalization.'),
      '#required' => TRUE,
    ];

    $form['language_behavior'] = [
      '#type' => 'select',
      '#title' => $this->t('Language behavior'),
      '#options' => [
        'per_translation' => $this->t('Per translation (one KnowledgeItem per translation)'),
        'default' => $this->t('Default language only'),
      ],
      '#default_value' => (string) $config->get('language_behavior') ?: 'per_translation',
    ];

    $form['authority_level'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Authority level'),
      '#default_value' => (string) $config->get('authority_level') ?: 'canonical',
      '#description' => $this->t('Stored on KnowledgeItems for downstream consumers (e.g., canonical, derived).'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $bundles = array_filter($form_state->getValue('bundles') ?? []);
    if ($form_state->getValue('enabled') && empty($bundles)) {
      $form_state->setErrorByName('bundles', $this->t('Select at least one content type when enabling the node adapter.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $bundles = array_filter($form_state->getValue('bundles') ?? []);
    $fields = [
      'title' => (string) $form_state->getValue(['fields', 'title']),
      'body' => (string) $form_state->getValue(['fields', 'body']),
    ];

    $this->config('bm_knowledge_ai.node_adapter')
      ->set('enabled', (bool) $form_state->getValue('enabled'))
      ->set('bundles', $bundles)
      ->set('fields', $fields)
      ->set('language_behavior', (string) $form_state->getValue('language_behavior'))
      ->set('authority_level', (string) $form_state->getValue('authority_level'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Returns node bundles keyed by machine name.
   *
   * @return array<string, string>
   *   Bundle options.
   */
  protected function getNodeBundles(): array {
    $bundles = [];
    if (!$this->moduleHandler->moduleExists('node')) {
      return $bundles;
    }
    $storage = $this->entityTypeManager->getStorage('node_type');
    foreach ($storage->loadMultiple() as $type) {
      $bundles[$type->id()] = $type->label();
    }
    return $bundles;
  }

}
