<?php

namespace Drupal\website_feedback\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Website Feedback Admin Note entity.
 *
 * @ContentEntityType(
 *   id = "website_feedback_note",
 *   label = @Translation("Website Feedback Admin Note"),
 *   label_collection = @Translation("Website Feedback Admin Notes"),
 *   handlers = {},
 *   base_table = "website_feedback_note",
 *   admin_permission = "administer website feedback",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "note",
 *     "uid" = "uid"
 *   }
 * )
 */
class WebsiteFeedbackNote extends ContentEntityBase {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave(
    EntityStorageInterface $storage
  ) {
    parent::preSave($storage);

    /*
     * Store the current user as the note author
     * when the note is created.
     */
    if ($this->isNew() && $this->get('uid')->isEmpty()) {
      $this->set(
        'uid',
        \Drupal::currentUser()->id()
      );
    }

    /*
     * Store the creation timestamp.
     */
    if ($this->isNew() && $this->get('created')->isEmpty()) {
      $this->set(
        'created',
        \Drupal::time()->getRequestTime()
      );
    }
  }

  /**
   * Returns the note text.
   */
  public function getNote(): string {
    return (string) $this->get('note')->value;
  }

  /**
   * Returns the parent feedback ID.
   */
  public function getFeedbackId(): int {
    return (int) $this->get('feedback_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(
    EntityTypeInterface $entity_type
  ) {
    $fields = parent::baseFieldDefinitions($entity_type);

    /*
     * Entity ID.
     */
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(
        t('The admin note entity ID.')
      )
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE)
      ->setSetting('auto_increment', TRUE);

    /*
     * UUID.
     */
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(
        t('The UUID of the admin note entity.')
      )
      ->setReadOnly(TRUE);

    /*
     * Parent feedback.
     */
    $fields['feedback_id'] = BaseFieldDefinition::create(
      'entity_reference'
    )
      ->setLabel(t('Feedback'))
      ->setDescription(
        t('The feedback associated with this admin note.')
      )
      ->setRequired(TRUE)
      ->setSetting(
        'target_type',
        'website_feedback'
      )
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => -10,
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ]);

    /*
     * Admin note.
     */
    $fields['note'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Admin Note'))
      ->setDescription(
        t('Internal note maintained by administrators.')
      )
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 0,
      ]);

    /*
     * Note author.
     */
    $fields['uid'] = BaseFieldDefinition::create(
      'entity_reference'
    )
      ->setLabel(t('Added By'))
      ->setDescription(
        t('The administrator who added the note.')
      )
      ->setSetting(
        'target_type',
        'user'
      )
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 10,
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ]);

    /*
     * Created timestamp.
     */
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(
        t('The date and time when the note was created.')
      )
      ->setReadOnly(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ]);

    /*
     * Last changed timestamp.
     */
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'));

    return $fields;
  }

}