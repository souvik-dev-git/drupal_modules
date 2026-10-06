<?php

namespace Drupal\content_access_restriction\EventSubscriber;

use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Content access restriction with cache safety.
 */
class ContentAccessSubscriber implements EventSubscriberInterface {

  /**
   * Checks page access restrictions.
   */
  public function onRequest(RequestEvent $event) {

    if (!$event->isMainRequest()) {
      return;
    }

    $route_match = \Drupal::routeMatch();

    if ($route_match->getRouteName() !== 'entity.node.canonical') {
      return;
    }

    $node = $route_match->getParameter('node');

    if (!$node instanceof NodeInterface) {
      return;
    }

    $current_user = \Drupal::currentUser();
    $current_uid = (int) $current_user->id();

    $config = \Drupal::config('content_access_restriction.settings');
    $enabled_types = $config->get('enabled_content_types') ?? [];

    // Skip if this content type is not enabled.
    if (!in_array($node->bundle(), $enabled_types, TRUE)) {
      return;
    }

    // Restricted roles.
    $restricted_roles = array_column(
      $node->get('field_restrict_roles')->getValue(),
      'value'
    );

    // Restricted users.
    $restricted_users = array_column(
      $node->get('field_restrict_users')->getValue(),
      'target_id'
    );

    // Restriction action.
    $action = $node->get('field_restrict_action')->value ?? '403';

    // Administrator bypass.
    $bypass_admin = (bool) ($node->get('field_restrict_bypass_admin')->value ?? 0);

    // Allow administrator if bypass is enabled.
    if ($bypass_admin && $current_user->hasRole('administrator')) {
      return;
    }

    $user_roles = $current_user->getRoles();

    // Check role restriction.
    $is_role_blocked = !empty(array_intersect(
      $user_roles,
      $restricted_roles
    ));

    // Check user restriction.
    $is_user_blocked = in_array(
      $current_uid,
      array_map('intval', $restricted_users),
      TRUE
    );

    // Restrict if either role or user matches.
    if ($is_role_blocked || $is_user_blocked) {

      if ($action === '404') {
        throw new NotFoundHttpException();
      }

      throw new AccessDeniedHttpException();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onRequest', 30],
    ];
  }

}