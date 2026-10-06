<?php

namespace Drupal\website_feedback\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\website_feedback\Entity\WebsiteFeedback;

/**
 * Provides the Website Experience Feedback form.
 */
class WebsiteFeedbackForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'website_feedback_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['#prefix'] = '<div id="website-feedback-form-wrapper">';
    $form['#suffix'] = '</div>';

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#required' => TRUE,
      '#maxlength' => 150,
      '#attributes' => [
        'placeholder' => $this->t('Enter a short title'),
        'class' => ['website-feedback-title'],
      ],
    ];

    $form['rating'] = [
      '#type' => 'radios',
      '#title' => $this->t('Your Rating'),
      '#options' => [
        1 => '1 Star',
        2 => '2 Stars',
        3 => '3 Stars',
        4 => '4 Stars',
        5 => '5 Stars',
      ],
      '#attributes' => [
        'class' => ['star-rating-container'],
      ],
      '#required' => TRUE,
    ];

    $form['feedback_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Feedback Type'),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select feedback type -'),
      '#options' => [
        'improvement' => $this->t('Suggest an improvement'),
        'portal_issue' => $this->t('Report a portal issue'),
        'positive' => $this->t('Share positive feedback'),
      ],
    ];

    $form['feedback'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Your Feedback'),
      '#required' => TRUE,
      '#maxlength' => 5000,
      '#attributes' => [
        'placeholder' => $this->t('Tell us about your experience...'),
        'rows' => 6,
      ],
    ];

    /*
     * Full page screenshot button.
     *
     * This is handled entirely by JavaScript.
     */
    $form['screenshot'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['website-feedback-screenshot-container'],
      ],
    ];

    $form['screenshot']['button'] = [
      '#type' => 'button',
      '#value' => $this->t('Take Full Page Screenshot'),
      '#attributes' => [
        'class' => [
          'website-feedback-screenshot-button',
        ],
        'type' => 'button',
      ],
    ];

    $form['image_attachment'] = [
      '#type' => 'file',
      '#title' => $this->t('Image Attachments'),
      '#multiple' => TRUE,
      '#attributes' => [
        'class' => ['website-feedback-image-upload'],
        'accept' => 'image/png,image/jpeg,image/webp',
      ],
    ];

    $form['uploaded_image_fids'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'class' => ['website-feedback-image-fids'],
      ],
      '#default_value' => '',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit Feedback'),
      '#attributes' => [
        'class' => ['website-feedback-submit'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // submit handled by controller as it is ajax
  }

}