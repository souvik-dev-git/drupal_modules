<?php

namespace Drupal\content_access_restriction\EventSubscriber;

use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Content access restriction with cache safety.
 */
class ContentAccessSubscriber implements EventSubscriberInterface {

  /**
   * Checks access for restricted content.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
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

    $config = \Drupal::config('content_access_restriction.settings');
    $enabled_types = $config->get('enabled_content_types') ?? [];

    if (!in_array($node->bundle(), $enabled_types, TRUE)) {
      return;
    }

    $restricted_roles = array_column(
      $node->get('field_restrict_roles')->getValue(),
      'value'
    );

    $restricted_usernames = array_filter(array_map(
      'trim',
      explode(',', (string) ($node->get('field_restrict_usernames')->value ?? ''))
    ));
    $restricted_usernames = array_map('mb_strtolower', $restricted_usernames);

    $action = $node->get('field_restrict_action')->value ?? '403';
    $bypass_admin = (bool) $node->get('field_restrict_bypass_admin')->value;

    $user_roles = $current_user->getRoles();

    if ($bypass_admin && $current_user->hasRole('administrator')) {
      return;
    }

    $current_username = mb_strtolower($current_user->getAccountName());
    $is_blocked_by_role = !empty(array_intersect($user_roles, $restricted_roles));
    $is_blocked_by_username = in_array($current_username, $restricted_usernames, TRUE);

    $is_blocked = $is_blocked_by_role || $is_blocked_by_username;

    if ($is_blocked) {
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