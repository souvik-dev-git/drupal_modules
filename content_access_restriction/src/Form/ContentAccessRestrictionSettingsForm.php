<?php

namespace Drupal\content_access_restriction\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form.
 */
class ContentAccessRestrictionSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'content_access_restriction_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      'content_access_restriction.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $config = $this->config('content_access_restriction.settings');

    $content_types = [];

    // foreach ($this->entityTypeManager()
    //   ->getStorage('node_type')
    //   ->loadMultiple() as $type) {

    //   $content_types[$type->id()] = $type->label();
    // }

    $node_types = \Drupal::entityTypeManager()
  ->getStorage('node_type')
  ->loadMultiple();

$content_types = [];

foreach ($node_types as $type) {
  $content_types[$type->id()] = $type->label();
}

    $form['enabled_content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enable restriction for content types'),
      '#options' => $content_types,
      '#default_value' => $config->get('enabled_content_types') ?? [],
      '#description' => $this->t('Only these content types will use Content Access Restriction.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    $enabled = array_filter($form_state->getValue('enabled_content_types'));

    $this->configFactory()
      ->getEditable('content_access_restriction.settings')
      ->set('enabled_content_types', $enabled)
      ->save();

    parent::submitForm($form, $form_state);
  }

}