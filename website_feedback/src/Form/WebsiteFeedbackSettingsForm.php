<?php

namespace Drupal\website_feedback\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for Website Feedback.
 */
class WebsiteFeedbackSettingsForm extends ConfigFormBase {

  /**
   * Configuration name.
   */
  protected const CONFIG_NAME = 'website_feedback.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'website_feedback_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      self::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(self::CONFIG_NAME);

    $form['feedback_button_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Feedback Popup'),
      '#description' => $this->t(
        'Enable or disable the feedback popup on the website.'
      ),
      '#default_value' => $config->get('feedback_button_enabled') ?? TRUE,
    ];

    $keywords = $config->get('critical_keywords') ?: [];

    $form['critical_keywords'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Critical Priority Keywords'),
      '#description' => $this->t(
        'Enter one keyword or phrase per line. If the submitted feedback contains any of these keywords, the feedback will automatically be assigned Critical priority.'
      ),
      '#default_value' => implode("\n", $keywords),
      '#rows' => 10,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $keywords = preg_split(
      '/\r\n|\r|\n/',
      $form_state->getValue('critical_keywords')
    );

    $keywords = array_map('trim', $keywords);

    $keywords = array_filter(
      $keywords,
      static function ($keyword) {
        return $keyword !== '';
      }
    );

    $keywords = array_values(array_unique($keywords));

    $this->configFactory
      ->getEditable(self::CONFIG_NAME)
      ->set(
        'feedback_button_enabled',
        (bool) $form_state->getValue('feedback_button_enabled')
      )
      ->set('critical_keywords', $keywords)
      ->save();

    parent::submitForm($form, $form_state);
  }

}