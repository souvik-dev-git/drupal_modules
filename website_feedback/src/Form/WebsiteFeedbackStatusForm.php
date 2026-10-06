<?php

namespace Drupal\website_feedback\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\website_feedback\Entity\WebsiteFeedback;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for updating Website Feedback ticket information.
 */
class WebsiteFeedbackStatusForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs the form.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'website_feedback_status_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    WebsiteFeedback $website_feedback = NULL
  ) {
    if (!$website_feedback) {
      $this->messenger()->addError(
        $this->t('Feedback could not be found.')
      );

      return $form;
    }

    $form['feedback_id'] = [
      '#type' => 'hidden',
      '#value' => $website_feedback->id(),
    ];

    $form['feedback_reference'] = [
      '#type' => 'item',
      '#title' => $this->t('Feedback Reference'),
      '#markup' => '<strong>' .
        htmlspecialchars(
          $website_feedback->get('feedback_reference')->value,
          ENT_QUOTES,
          'UTF-8'
        ) .
        '</strong>',
    ];

    /*
     * Current Status.
     */
    $current_status = $website_feedback->get('status')->value ?: 'new';

    $status_allowed_values = $website_feedback
      ->getFieldDefinition('status')
      ->getSetting('allowed_values') ?: [];

    $current_status_label = $status_allowed_values[$current_status]
      ?? $current_status;

    $form['current_status'] = [
      '#type' => 'item',
      '#title' => $this->t('Current Status'),
      '#markup' => '<strong>' .
        htmlspecialchars(
          $current_status_label,
          ENT_QUOTES,
          'UTF-8'
        ) .
        '</strong>',
    ];

    /*
     * Status.
     */
    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#required' => TRUE,
      '#options' => [
        'new' => $this->t('New'),
        'under_review' => $this->t('Under Review'),
        'in_progress' => $this->t('In Progress'),
        'resolved' => $this->t('Resolved'),
        'closed' => $this->t('Closed'),
        'canceled' => $this->t('Canceled'),
      ],
      '#default_value' => $current_status,
    ];

    /*
     * Impact.
     */
    $current_impact = $website_feedback->get('impact')->value ?: '2';

    $impact_allowed_values = $website_feedback
      ->getFieldDefinition('impact')
      ->getSetting('allowed_values') ?: [];

    $form['impact'] = [
      '#type' => 'select',
      '#title' => $this->t('Impact'),
      '#required' => TRUE,
      '#options' => $impact_allowed_values ?: [
        '1' => $this->t('High'),
        '2' => $this->t('Medium'),
        '3' => $this->t('Low'),
      ],
      '#default_value' => $current_impact,
    ];

    /*
     * Urgency.
     */
    $current_urgency = $website_feedback->get('urgency')->value ?: '2';

    $urgency_allowed_values = $website_feedback
      ->getFieldDefinition('urgency')
      ->getSetting('allowed_values') ?: [];

    $form['urgency'] = [
      '#type' => 'select',
      '#title' => $this->t('Urgency'),
      '#required' => TRUE,
      '#options' => $urgency_allowed_values ?: [
        '1' => $this->t('High'),
        '2' => $this->t('Medium'),
        '3' => $this->t('Low'),
      ],
      '#default_value' => $current_urgency,
    ];

    /*
    * Admin Note.
    *
    * This is a new note for the current ticket update.
    * Existing notes are never loaded into this field.
    */
    $form['admin_notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Admin Note'),
      '#description' => $this->t(
        'Add an internal note for this ticket update.'
      ),
      '#default_value' => '',
      '#rows' => 6,
      '#maxlength' => 5000,
    ];

    /*
     * Actions.
     */
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update Ticket'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute(
        'entity.website_feedback.canonical',
        [
          'website_feedback' => $website_feedback->id(),
        ]
      ),
      '#attributes' => [
        'class' => [
          'button',
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(
    array &$form,
    FormStateInterface $form_state
  ) {
    $status = $form_state->getValue('status');

    $admin_notes = trim(
      (string) $form_state->getValue('admin_notes')
    );

    if (
      in_array(
        $status,
        ['resolved', 'closed', 'canceled'],
        TRUE
      )
      && $admin_notes === ''
    ) {
      $form_state->setErrorByName(
        'admin_notes',
        $this->t(
          'Admin Notes are required when the feedback status is Resolved or Closed or Canceled.'
        )
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(
    array &$form,
    FormStateInterface $form_state
  ) {
    $feedback_id = $form_state->getValue('feedback_id');

    $new_status = $form_state->getValue('status');
    $impact = $form_state->getValue('impact');
    $urgency = $form_state->getValue('urgency');

    $admin_notes = trim(
      (string) $form_state->getValue('admin_notes')
    );

    $feedback = $this->entityTypeManager
      ->getStorage('website_feedback')
      ->load($feedback_id);

    if (!$feedback) {
      $this->messenger()->addError(
        $this->t('Feedback could not be found.')
      );

      return;
    }

    /*
    * Update the feedback ticket.
    */
    $feedback->set('status', $new_status);
    $feedback->set('impact', $impact);
    $feedback->set('urgency', $urgency);

    $feedback->save();

    /*
    * Create a new admin note.
    *
    * Each submission creates a separate note.
    * Existing notes are never overwritten.
    */
    if ($admin_notes !== '') {
      $note_storage = $this->entityTypeManager
        ->getStorage('website_feedback_note');

      $note = $note_storage->create([
        'feedback_id' => $feedback->id(),
        'note' => $admin_notes,
        'uid' => $this->currentUser()->id(),
      ]);

      $note->save();
    }

    $this->messenger()->addStatus(
      $this->t(
        'Ticket has been updated successfully.'
      )
    );

    $form_state->setRedirect(
      'entity.website_feedback.canonical',
      [
        'website_feedback' => $feedback->id(),
      ]
    );
  }

}