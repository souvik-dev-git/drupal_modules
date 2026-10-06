<?php

namespace Drupal\site_feedback\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a confirmation form for deleting Site Feedback.
 */
class SiteFeedbackDeleteForm extends ConfirmFormBase {

    protected $feedbackId;

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'site_feedback_delete_form';
    }

    /**
     * {@inheritdoc}
     */
    public function getQuestion() {
        $feedback = $this->getFeedback();

        if (!$feedback) {
            return $this->t('Are you sure you want to delete this feedback?');
        }

        return $this->t('Are you sure you want to delete feedback @reference?', ['@reference' => $feedback->get('feedback_reference')->value,]);
    }

    /**
     * {@inheritdoc}
     */
    public function getCancelUrl() {
        return new Url('site_feedback.collection');
    }

    /**
     * {@inheritdoc}
     */
    public function getConfirmText() {
        return $this->t('Delete');
    }

    /**
     * {@inheritdoc}
     */
        public function buildForm(array $form, FormStateInterface $form_state) {
        $this->feedbackId = $this->getRouteMatch()->getParameter('site_feedback');

        return parent::buildForm($form, $form_state);
    }

    protected function getFeedback() {
        if (!$this->feedbackId) {
        $this->feedbackId = $this->getRouteMatch()->getParameter('site_feedback');
        }

        if (!$this->feedbackId) {
        return NULL;
        }

        return \Drupal::entityTypeManager()
        ->getStorage('site_feedback')
        ->load($this->feedbackId);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $feedback = $this->getFeedback();

        if (!$feedback) {
        $this->messenger()->addError(
            $this->t('The feedback could not be found.')
        );

        $form_state->setRedirect('site_feedback.collection');
            return;
        }

        $reference = $feedback->get('feedback_reference')->value;

        if (!$feedback->get('attachments')->isEmpty()) {
            foreach ($feedback->get('attachments') as $attachment) {
                $file = $attachment->entity;

                if (!$file) {
                    continue;
                }

                $uri = $file->getFileUri();

                if (str_starts_with($uri, 'public://site_feedback/')) {
                    $file->delete();
                }
            }
        }

        $feedback->delete();

        $this->messenger()->addStatus($this->t('Feedback @reference has been deleted.', ['@reference' => $reference,]));

        $form_state->setRedirect('site_feedback.collection');
    }

}