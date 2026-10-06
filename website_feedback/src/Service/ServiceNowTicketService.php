<?php

namespace Drupal\website_feedback\Service;

use Drupal\website_feedback\Entity\WebsiteFeedback;

/**
 * Provides a mock ServiceNow ticket service.
 *
 * This service is used for the ServiceNow POC.
 * It does not connect to a real ServiceNow instance.
 */
class ServiceNowTicketService {

  /**
   * Creates a mock ServiceNow incident.
   *
   * @param \Drupal\website_feedback\Entity\WebsiteFeedback $feedback
   *   The Website Feedback entity.
   *
   * @return array
   *   Mock ServiceNow ticket response.
   */
  public function createTicket(
    WebsiteFeedback $feedback
  ): array {

    $feedback_id = (int) $feedback->id();

    /*
     * Generate a predictable mock Incident number.
     *
     * Example:
     * Feedback ID 1  -> INC0010001
     * Feedback ID 25 -> INC0010025
     */
    $ticket_number = 'INC' . str_pad(
      (string) (10000 + $feedback_id),
      7,
      '0',
      STR_PAD_LEFT
    );

    /*
     * Generate a mock ServiceNow sys_id.
     *
     * This is NOT a real ServiceNow sys_id.
     */
    $ticket_id = 'mock-' . $feedback->uuid();

    return [
      'success' => TRUE,
      'ticket_number' => $ticket_number,
      'ticket_id' => $ticket_id,
      'status' => 'created',
      'message' => 'Mock ServiceNow incident created successfully.',
    ];
  }

}