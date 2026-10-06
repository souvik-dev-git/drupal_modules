<?php

namespace Drupal\website_feedback\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Website Feedback entity.
 *
 * @ContentEntityType(
 *   id = "website_feedback",
 *   label = @Translation("Website Feedback"),
 *   label_collection = @Translation("Website Feedback"),
 *   handlers = {
 *     "list_builder" = "Drupal\website_feedback\WebsiteFeedbackListBuilder"
 *   },
 *   base_table = "website_feedback",
 *   admin_permission = "administer website feedback",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "title"
 *   },
 *   links = {
 *     "canonical" = "/admin/content/website-feedback/{website_feedback}",
 *     "collection" = "/admin/content/website-feedback"
 *   }
 * )
 */
class WebsiteFeedback extends ContentEntityBase {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    /*
     * Generate the feedback reference only when the entity
     * is being created for the first time.
     */
    if ($this->isNew() && $this->get('feedback_reference')->isEmpty()) {
      $uuid = str_replace('-', '', $this->uuid());

      $reference = sprintf(
        'WFB-%s-%s',
        date('Ymd'),
        strtoupper(substr($uuid, 0, 8))
      );

      $this->set('feedback_reference', $reference);
    }

    /*
     * Default status.
     */
    if ($this->get('status')->isEmpty()) {
      $this->set('status', 'new');
    }

    /*
     * Default priority.
     */
    if ($this->get('priority')->isEmpty()) {
      $this->set('priority', 'medium');
    }

    /*
     * Default impact.
     */
    if ($this->get('impact')->isEmpty()) {
      $this->set('impact', 'medium');
    }

    /*
     * Default urgency.
     */
    if ($this->get('urgency')->isEmpty()) {
      $this->set('urgency', 'medium');
    }

    /*
     * Store submission date.
     */
    if ($this->isNew() && $this->get('submitted_date')->isEmpty()) {
      $this->set(
        'submitted_date',
        \Drupal::time()->getRequestTime()
      );
    }
  }

  /**
   * Returns the generated feedback reference.
   */
  public function getFeedbackReference(): string {
    return (string) $this->get('feedback_reference')->value;
  }

  /**
   * Returns the feedback status.
   */
  public function getStatus(): string {
    return (string) $this->get('status')->value;
  }

  /**
   * Returns the feedback priority.
   */
  public function getPriority(): string {
    return (string) $this->get('priority')->value;
  }

  /**
   * Returns the feedback impact.
   */
  public function getImpact(): string {
    return (string) $this->get('impact')->value;
  }

  /**
   * Returns the feedback urgency.
   */
  public function getUrgency(): string {
    return (string) $this->get('urgency')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    /*
     * Entity ID.
     */
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The feedback entity ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE)
      ->setSetting('auto_increment', TRUE);

    /*
     * UUID.
     */
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the feedback entity.'))
      ->setReadOnly(TRUE);

    /*
     * Feedback reference.
     */
    $fields['feedback_reference'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Feedback Reference'))
      ->setDescription(
        t('Unique reference generated for the feedback submission.')
      )
      ->setRequired(TRUE)
      ->setReadOnly(TRUE)
      ->setSetting('max_length', 40)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -20,
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ]);

    /*
     * Title.
     */
    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 150)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -10,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -10,
      ]);

    /*
     * Rating.
     */
    $fields['rating'] = BaseFieldDefinition::create('list_integer')
      ->setLabel(t('Rating'))
      ->setRequired(TRUE)
      ->setSettings([
        'allowed_values' => [
          1 => '1 Star',
          2 => '2 Stars',
          3 => '3 Stars',
          4 => '4 Stars',
          5 => '5 Stars',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => 0,
      ]);

    /*
     * Feedback type.
     */
    $fields['feedback_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Feedback Type'))
      ->setRequired(TRUE)
      ->setSettings([
        'allowed_values' => [
          'improvement' => 'Improvement Suggestion',
          'portal_issue' => 'Portal Issue',
          'positive' => 'Positive Feedback',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 10,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 10,
      ]);

    /*
     * Feedback description.
     */
    $fields['feedback'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Feedback'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 20,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 20,
      ]);

    /*
     * Status.
     */
    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setRequired(TRUE)
      ->setSettings([
        'allowed_values' => [
          'new' => 'New',
          'under_review' => 'Under Review',
          'in_progress' => 'In Progress',
          'resolved' => 'Resolved',
          'closed' => 'Closed',
          'canceled' => 'Canceled',
        ],
      ])
      ->setDefaultValue('new')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 30,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 30,
      ]);

    /*
     * Priority.
     */
    $fields['priority'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Priority'))
      ->setRequired(TRUE)
      ->setSettings([
        'allowed_values' => [
          '3' => 'Low',
          '2' => 'Medium',
          '1' => 'High',
        ],
      ])
      ->setDefaultValue('medium')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 40,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 40,
      ]);

    /*
     * Impact.
     */
    $fields['impact'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Impact'))
      ->setDescription(
        t('Automatically calculated impact of the feedback.')
      )
      ->setRequired(TRUE)
      ->setSettings([
        'allowed_values' => [
          '3' => 'Low',
          '2' => 'Medium',
          '1' => 'High',
        ],
      ])
      ->setDefaultValue('medium')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 50,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 50,
      ]);

    /*
     * Urgency.
     */
    $fields['urgency'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Urgency'))
      ->setDescription(
        t('Automatically calculated urgency of the feedback.')
      )
      ->setRequired(TRUE)
      ->setSettings([
        'allowed_values' => [
          '3' => 'Low',
          '2' => 'Medium',
          '1' => 'High',
        ],
      ])
      ->setDefaultValue('medium')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 60,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 60,
      ]);

    /*
     * Submitted by.
     *
     * We use "uid" because EntityOwnerTrait expects
     * the owner field to be named "uid".
     */
    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Submitted By'))
      ->setDescription(
        t('The authenticated user who submitted the feedback.')
      )
      ->setSetting('target_type', 'user')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 70,
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ]);

    /*
     * Submitted date.
     */
    $fields['submitted_date'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Submitted Date'))
      ->setDescription(
        t('Date and time when the feedback was submitted.')
      )
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 80,
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ]);

    /*
     * Source URL.
     */
    $fields['source_url'] = BaseFieldDefinition::create('uri')
      ->setLabel(t('Source URL'))
      ->setDescription(
        t('Portal page from which the feedback was submitted.')
      )
      ->setSetting('max_length', 2048)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'uri_link',
        'weight' => 90,
      ])
      ->setDisplayOptions('form', [
        'type' => 'uri',
        'weight' => 90,
      ]);

    /*
     * Assigned portal team member.
     */
    $fields['assigned_to'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Assigned To'))
      ->setSetting('target_type', 'user')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 100,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 100,
      ]);

    /*
     * Uploaded screenshots/images.
     */
    $fields['attachments'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Attachments'))
      ->setSetting('target_type', 'file')
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_entity_view',
        'weight' => 120,
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ]);

    /*
     * Last changed timestamp.
     */
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'));

    $fields['servicenow_ticket_number'] = BaseFieldDefinition::create('string')
      ->setLabel(t('ServiceNow Ticket Number'))
      ->setDescription(
        t('Ticket number returned by ServiceNow.')
      )
      ->setRequired(FALSE)
      ->setSettings([
        'max_length' => 50,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 100,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 100,
      ]);

    $fields['servicenow_ticket_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('ServiceNow Ticket ID'))
      ->setDescription(
        t('System ID returned by ServiceNow.')
      )
      ->setRequired(FALSE)
      ->setSettings([
        'max_length' => 128,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 110,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 110,
      ]);

    // ServiceNow Fields  

    $fields['servicenow_integration_status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('ServiceNow Integration Status'))
      ->setDescription(
        t('Current status of the ServiceNow ticket integration.')
      )
      ->setRequired(FALSE)
      ->setSettings([
        'allowed_values' => [
          'pending' => 'Pending',
          'created' => 'Created',
          'failed' => 'Failed',
          'not_required' => 'Not Required',
        ],
      ])
      ->setDefaultValue('pending')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 120,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 120,
      ]);

    return $fields;
  }

}