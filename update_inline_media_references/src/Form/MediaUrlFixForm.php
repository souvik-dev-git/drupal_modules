<?php

namespace Drupal\update_inline_media_references\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media\Entity\MediaType;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MediaUrlFixForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container
  ) {
    return new static();
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'update_inline_media_references_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state
  ): array {

    /**
     * Content types.
     */
    $content_types = \Drupal::service('entity_type.bundle.info')
      ->getBundleInfo('node');

    $content_type_options = [];

    foreach ($content_types as $machine_name => $info) {

      $content_type_options[$machine_name] = $info['label'];

    }

    /**
     * Dynamic media types.
     */
    $media_type_options = [];

    $media_types = MediaType::loadMultiple();

    foreach ($media_types as $media_type) {

      $source_configuration = $media_type
        ->getSource()
        ->getConfiguration();

      $source_field = $source_configuration['source_field'] ?? NULL;

      if (!$source_field) {
        continue;
      }

      $field_config = \Drupal::service('entity_field.manager')
        ->getFieldDefinitions(
          'media',
          $media_type->id()
        );

      if (
        empty($field_config[$source_field])
      ) {
        continue;
      }

      $field_definition = $field_config[$source_field];

      $field_settings = $field_definition
        ->getSettings();

      $extensions = $field_settings['file_extensions'] ?? '';

      if (empty($extensions)) {
        continue;
      }

      $extensions_array = array_filter(
        explode(' ', $extensions)
      );

      $media_type_options[$media_type->id()] =
        $media_type->label() .
        ' (' .
        implode(', ', $extensions_array) .
        ')';
    }

    asort($media_type_options);

    $form['description'] = [
      '#markup' => '
        <p>
          This tool updates migrated inline media URLs
          inside formatted HTML fields.
        </p>
      ',
    ];

    /**
     * Content types.
     */
    $form['content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content types'),
      '#options' => $content_type_options,
      '#required' => TRUE,
    ];

    /**
     * Media types.
     */
    $form['media_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Media types to update'),
      '#options' => $media_type_options,
      '#required' => TRUE,
      '#description' => $this->t(
        'Only selected media type file extensions will be updated.'
      ),
    ];

    /**
     * Submit.
     */
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update Media References'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(
    array &$form,
    FormStateInterface $form_state
  ): void {

    $selected_types = array_filter(
      $form_state->getValue('content_types')
    );

    $selected_media_types = array_filter(
      $form_state->getValue('media_types')
    );

    /**
     * Build extensions dynamically.
     */
    $allowed_extensions = [];

    $media_types = MediaType::loadMultiple(
      $selected_media_types
    );

    foreach ($media_types as $media_type) {

      $source_configuration = $media_type
        ->getSource()
        ->getConfiguration();

      $source_field = $source_configuration['source_field'] ?? NULL;

      if (!$source_field) {
        continue;
      }

      $field_config = \Drupal::service('entity_field.manager')
        ->getFieldDefinitions(
          'media',
          $media_type->id()
        );

      if (
        empty($field_config[$source_field])
      ) {
        continue;
      }

      $field_definition = $field_config[$source_field];

      $field_settings = $field_definition
        ->getSettings();

      $extensions = $field_settings['file_extensions'] ?? '';

      if (empty($extensions)) {
        continue;
      }

      $extensions_array = array_filter(
        explode(' ', $extensions)
      );

      $allowed_extensions = array_merge(
        $allowed_extensions,
        $extensions_array
      );
    }

    $allowed_extensions = array_unique(
      array_map(
        'strtolower',
        $allowed_extensions
      )
    );

    $nids = \Drupal::entityQuery('node')
      ->condition(
        'type',
        $selected_types,
        'IN'
      )
      ->accessCheck(FALSE)
      ->execute();

    $operations = [];

    foreach (array_chunk($nids, 25) as $chunk) {

      $operations[] = [
        [
          static::class,
          'processBatch',
        ],
        [
          $chunk,
          $allowed_extensions,
        ],
      ];
    }

    $batch = [
      'title' => $this->t(
        'Updating media references'
      ),
      'operations' => $operations,
      'finished' => [
        static::class,
        'finishBatch',
      ],
      'progress_message' => $this->t(
        'Processing content updates...'
      ),
      'error_message' => $this->t(
        'Some content could not be processed.'
      ),
    ];

    batch_set($batch);
  }

  /**
   * Batch processor.
   */
  public static function processBatch(
    array $nids,
    array $allowed_extensions,
    array &$context
  ): void {

    $processor = \Drupal::service(
      'update_inline_media_references.batch_processor'
    );

    $processor->process(
      $nids,
      $allowed_extensions,
      $context
    );
  }

  /**
   * Batch finished callback.
   */
  public static function finishBatch(
    $success,
    array $results,
    array $operations
  ): void {

    if ($success) {

      \Drupal::messenger()->addStatus(
        t(
          'Media references updated in @count entities.',
          [
            '@count' => $results['updated'] ?? 0,
          ]
        )
      );

    }
    else {

      \Drupal::messenger()->addError(
        t('Some items failed during processing.')
      );

    }
  }

}