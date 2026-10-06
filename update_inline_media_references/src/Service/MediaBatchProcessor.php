<?php

// src/Service/MediaBatchProcessor.php

namespace Drupal\update_inline_media_references\Service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

class MediaBatchProcessor {

  /**
   * Entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * URL fixer service.
   */
  protected MediaUrlFixer $fixer;

  /**
   * Logger.
   */
  protected $logger;

  /**
   * Constructor.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MediaUrlFixer $fixer,
    LoggerChannelFactoryInterface $logger_factory
  ) {

    $this->entityTypeManager = $entity_type_manager;

    $this->fixer = $fixer;

    $this->logger = $logger_factory->get(
      'update_inline_media_references'
    );

  }

  /**
   * Process batch.
   */
  public function process(
    array $nids,
    array $allowed_extensions,
    array &$context
  ): void {

    $updated_count = 0;

    $node_storage = $this->entityTypeManager
      ->getStorage('node');

    foreach ($nids as $nid) {

      try {

        $node = $node_storage->load($nid);

        if (!$node) {
          continue;
        }

        $updated = $this->processEntityFields(
          $node,
          $allowed_extensions
        );

        if ($updated) {

          $updated_count++;

        }

      }
      catch (\Exception $e) {

        $this->logger->error(
          'Failed processing node ID @nid. Error: @message',
          [
            '@nid' => $nid,
            '@message' => $e->getMessage(),
          ]
        );

      }
    }

    if (!isset($context['results']['updated'])) {

      $context['results']['updated'] = 0;

    }

    $context['results']['updated'] += $updated_count;
  }

  /**
   * Process entity fields recursively.
   */
  protected function processEntityFields(
    EntityInterface $entity,
    array $allowed_extensions
  ): bool {

    $entity_updated = FALSE;

    foreach ($entity->getFields() as $field_name => $field) {

      $field_definition = $field->getFieldDefinition();

      $field_type = $field_definition->getType();

      /**
       * Process HTML/text fields.
       */
      if (in_array($field_type, [
        'text',
        'text_long',
        'text_with_summary',
        'string_long',
      ])) {

        foreach ($field as $delta => $item) {

          $value = $item->value ?? '';

          if (empty($value)) {
            continue;
          }

          /**
           * Skip if no supported tags found.
           */
          if (
            stripos($value, '<img') === FALSE &&
            stripos($value, '<a') === FALSE &&
            stripos($value, '<video') === FALSE &&
            stripos($value, '<source') === FALSE &&
            stripos($value, '<iframe') === FALSE
          ) {
            continue;
          }

          $updated_html = $this->fixer
            ->rewriteHtml(
              $value,
              $allowed_extensions
            );

          if ($updated_html !== $value) {

            $field[$delta]->value = $updated_html;

            $entity_updated = TRUE;

          }
        }
      }

      /**
       * Process referenced entities recursively.
       */
      if (in_array($field_type, [
        'entity_reference',
        'entity_reference_revisions',
      ])) {

        foreach ($field as $item) {

          if (empty($item->entity)) {
            continue;
          }

          $referenced_entity = $item->entity;

          if (
            !$referenced_entity ||
            !$referenced_entity instanceof ContentEntityInterface
          ) {
            continue;
          }

          $child_updated = $this->processEntityFields(
            $referenced_entity,
            $allowed_extensions
          );

          if ($child_updated) {

            $referenced_entity->save();

            $entity_updated = TRUE;

          }
        }
      }
    }

    /**
     * Save current entity.
     */
    if ($entity_updated) {

      $entity->save();

    }

    return $entity_updated;
  }

}