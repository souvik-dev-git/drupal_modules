<?php

namespace Drupal\website_feedback\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for filtering Website Feedback entities.
 */
class WebsiteFeedbackFilterForm extends FormBase {
    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return "website_feedback_filter_form";
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $request = $this->getRequest();

        $form["filters"] = [
            "#type" => "container",
            "#attributes" => [
                "class" => ["website-feedback-filters"],
            ],
        ];

        $form["filters"]["feedback_reference"] = [
            "#type" => "textfield",
            "#title" => $this->t("Reference"),
            "#placeholder" => $this->t("Search by reference"),
            "#default_value" => $request->query->get("feedback_reference", ""),
        ];

        $form["filters"]["feedback_type"] = [
            "#type" => "select",
            "#title" => $this->t("Type"),
            "#options" => [
                "" => $this->t("- All -"),
                "improvement" => $this->t("Improvement Suggestion"),
                "portal_issue" => $this->t("Portal Issue"),
                "positive" => $this->t("Positive Feedback"),
            ],
            "#default_value" => $request->query->get("feedback_type", ""),
        ];

        $form["filters"]["status"] = [
            "#type" => "select",
            "#title" => $this->t("Status"),
            "#options" => [
                "" => $this->t("- All -"),
                "new" => $this->t("New"),
                "under_review" => $this->t("Under Review"),
                "in_progress" => $this->t("In Progress"),
                "resolved" => $this->t("Resolved"),
                "closed" => $this->t("Closed"),
                "canceled" => $this->t("Canceled"),
            ],
            "#default_value" => $request->query->get("status", ""),
        ];

        $form["filters"]["priority"] = [
            "#type" => "select",
            "#title" => $this->t("Priority"),
            "#options" => [
                "" => $this->t("- All -"),
                "1" => $this->t("High"),
                "2" => $this->t("Medium"),
                "3" => $this->t("Low"),
            ],
            "#default_value" => $request->query->get("priority", ""),
        ];

        $form["filters"]["impact"] = [
            "#type" => "select",
            "#title" => $this->t("Impact"),
            "#options" => [
                "" => $this->t("- All -"),
                "1" => $this->t("High"),
                "2" => $this->t("Medium"),
                "3" => $this->t("Low"),
            ],
            "#default_value" => $request->query->get("impact", ""),
        ];

        $form["filters"]["urgency"] = [
            "#type" => "select",
            "#title" => $this->t("Urgency"),
            "#options" => [
                "" => $this->t("- All -"),
                "1" => $this->t("High"),
                "2" => $this->t("Medium"),
                "3" => $this->t("Low"),
            ],
            "#default_value" => $request->query->get("urgency", ""),
        ];

        $form["actions"] = [
            "#type" => "actions",
        ];

        $form["actions"]["apply"] = [
            "#type" => "submit",
            "#value" => $this->t("Apply filters"),
        ];

        $form["actions"]["reset"] = [
            "#type" => "submit",
            "#value" => $this->t("Reset"),
            "#submit" => ["::resetFilters"],
            "#limit_validation_errors" => [],
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $query = [];

        $feedback_reference = trim(
            (string) $form_state->getValue("feedback_reference")
        );

        $feedback_type = $form_state->getValue("feedback_type");
        $status = $form_state->getValue("status");
        $priority = $form_state->getValue("priority");
        $impact = $form_state->getValue("impact");
        $urgency = $form_state->getValue("urgency");

        if ($feedback_reference !== "") {
            $query["feedback_reference"] = $feedback_reference;
        }

        if ($feedback_type !== "") {
            $query["feedback_type"] = $feedback_type;
        }

        if ($status !== "") {
            $query["status"] = $status;
        }

        if ($priority !== "") {
            $query["priority"] = $priority;
        }

        if ($impact !== "") {
            $query["impact"] = $impact;
        }

        if ($urgency !== "") {
            $query["urgency"] = $urgency;
        }

        $form_state->setRedirect("website_feedback.collection", [], ["query" => $query,]);
    }

    /**
     * Reset all filters.
     */
    public function resetFilters(array &$form, FormStateInterface $form_state) {
        $form_state->setRedirect("website_feedback.collection");
    }
}
