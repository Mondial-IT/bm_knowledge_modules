<?php

declare(strict_types=1);

namespace Drupal\bm_help_ai\Form;

use Drupal\bm_help_ai\Service\HelpMetadataImportService;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for BM Help AI.
 */
class HelpAiSettingsForm extends FormBase {

  public function __construct(private HelpMetadataImportService $importService) {}

  public static function create(ContainerInterface $container): self {
    return new static($container->get('bm_help_ai.help_metadata_import'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bm_help_ai_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['description'] = [
      '#markup' => $this->t('Manage BM Help AI helpers. Use the button below to refresh taxonomy metadata (labels, top-level, related, timestamps).'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['import'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import help metadata'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $result = $this->importService->import();
    $this->messenger()->addStatus($this->t('Metadata import complete. Imported: @imported. Updated: @updated. Missing: @missing.', [
      '@imported' => $result['imported'] ?? 0,
      '@updated' => $result['updated'] ?? 0,
      '@missing' => $result['not_found'] ?? 0,
    ]));
  }

}
