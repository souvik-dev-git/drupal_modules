<?php

namespace Drupal\site_feedback;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of Site Feedback entities.
 */
class SiteFeedbackListBuilder extends EntityListBuilder {
  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header["feedback_reference"] = $this->t("Reference");
    $header["title"] = $this->t("Title");
    $header["feedback_type"] = $this->t("Type");
    $header["rating"] = $this->t("Rating");
    $header["priority"] = $this->t("Priority");
    $header["impact"] = $this->t("Impact");
    $header["urgency"] = $this->t("Urgency");
    $header["status"] = $this->t("Status");
    $header["submitted_date"] = $this->t("Submitted");

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row["feedback_reference"] = $entity->get("feedback_reference")->value;

    $row["title"] = $entity->get("title")->value;

    // Feedback type label.
    $feedback_type = $entity->get("feedback_type")->value;

    $feedback_type_allowed_values =
        $entity
            ->getFieldDefinition("feedback_type")
            ->getSetting("allowed_values") ?:
        [];

    $feedback_type_label =
        $feedback_type_allowed_values[$feedback_type] ?? $feedback_type;

    $row["feedback_type"] = $feedback_type_label;

    // Rating.
    $row["rating"] = $entity->get("rating")->value;

    // Priority label.
    $priority = $entity->get("priority")->value;

    $priority_allowed_values =
        $entity
            ->getFieldDefinition("priority")
            ->getSetting("allowed_values") ?:
        [];

    $priority_label = $priority_allowed_values[$priority] ?? $priority;

    $row["priority"] = $priority_label;

    // Impact label.
    $impact = $entity->get("impact")->value;

    $impact_allowed_values = $entity->getFieldDefinition("impact")->getSetting("allowed_values") ? : [];

    $impact_label = $impact_allowed_values[$impact] ?? $impact;

    $row["impact"] = $impact_label;

    // Urgency label.
    $urgency = $entity->get("urgency")->value;

    $urgency_allowed_values = $entity->getFieldDefinition("urgency")->getSetting("allowed_values") ? : [];

    $urgency_label = $urgency_allowed_values[$urgency] ?? $urgency;

    $row["urgency"] = $urgency_label;

    // Status label.
    $status = $entity->get("status")->value;

    $status_allowed_values = $entity->getFieldDefinition("status")->getSetting("allowed_values") ? : [];

    $status_label = $status_allowed_values[$status] ?? $status;

    $row["status"] = $status_label;

    // Submitted date.
    $submitted_date = $entity->get("submitted_date")->value;

    $row["submitted_date"] = $submitted_date ? \Drupal::service("date.formatter")->format($submitted_date, "short") : "";

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
      $operations = [];

      if (\Drupal::currentUser()->hasPermission("view site feedback")) {
          $operations["view"] = [
              "title" => $this->t("View"),
              "url" => $entity->toUrl("canonical"),
              "weight" => 0,
          ];
      }

      if (\Drupal::currentUser()->hasPermission("delete site feedback")) {
          $operations["delete"] = [
              "title" => $this->t("Delete"),
              "url" => Url::fromRoute("site_feedback.delete", [
                  "site_feedback" => $entity->id(),
              ]),
              "weight" => 10,
          ];
      }

      return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = [];

    $build["filters"] = \Drupal::formBuilder()->getForm(
        'Drupal\site_feedback\Form\SiteFeedbackFilterForm'
    );

    $build["list"] = parent::render();

    return $build;
  }

    /**
     * {@inheritdoc}
     */
    protected function getEntityIds() {
      $storage = $this->storage;
      $query = $storage->getQuery();

      $query->accessCheck(true);

      $request = \Drupal::request();

      $feedback_reference = trim(
          $request->query->get("feedback_reference", "")
      );

      $feedback_type = $request->query->get("feedback_type");
      $status = $request->query->get("status");
      $priority = $request->query->get("priority");
      $impact = $request->query->get("impact");
      $urgency = $request->query->get("urgency");

      if ($feedback_reference !== "") {
          $query->condition(
              "feedback_reference",
              $feedback_reference,
              "CONTAINS"
          );
      }

      if (!empty($feedback_type)) {
          $query->condition("feedback_type", $feedback_type);
      }

      if (!empty($status)) {
          $query->condition("status", $status);
      }

      if (!empty($priority)) {
          $query->condition("priority", $priority);
      }

      if (!empty($impact)) {
          $query->condition("impact", $impact);
      }

      if (!empty($urgency)) {
          $query->condition("urgency", $urgency);
      }

      $query->sort($this->entityType->getKey("id"), "DESC");

      $query->pager($this->limit);

      return $query->execute();
    }
}
