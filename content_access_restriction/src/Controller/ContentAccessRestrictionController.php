<?php

namespace Drupal\content_access_restriction\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for Content Access Restriction pages.
 */
class ContentAccessRestrictionController extends ControllerBase {

/**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManagerService;

  /**
   * Date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * Constructs the controller.
   */
  public function __construct(
  EntityTypeManagerInterface $entity_type_manager,
  DateFormatterInterface $date_formatter,
) {
  $this->entityTypeManagerService = $entity_type_manager;
  $this->dateFormatter = $date_formatter;
}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
    );
  }
/**
 * Displays the Content Access Restriction administration page.
 */
public function overview() {

  $build = [];

  $menu_links = [];

  if ($this->currentUser()->hasPermission('administer content access restriction settings')) {
    $menu_links['content_access_restriction.settings'] = [
      'title' => $this->t('Settings'),
      'description' => $this->t('Configure the content types for which content access restriction is enabled.'),
    ];
  }

  if ($this->currentUser()->hasPermission('view restricted content')) {
    $menu_links['content_access_restriction.restricted_content'] = [
      'title' => $this->t('Restricted Content'),
      'description' => $this->t('View all content that has configured content access restrictions.'),
    ];
  }

  $items = [];

  foreach ($menu_links as $route_name => $data) {
    $url = Url::fromRoute($route_name);

    $items[] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['admin-item'],
      ],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'dt',
        '#attributes' => [
          'class' => ['admin-item__title'],
        ],
        'content' => [
          '#type' => 'link',
          '#title' => $data['title'],
          '#url' => $url,
          '#attributes' => [
            'class' => ['admin-item__link'],
            'title' => $data['description'],
          ],
        ],
      ],
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'dd',
        '#attributes' => [
          'class' => ['admin-item__description'],
        ],
        '#value' => $data['description'],
      ],
    ];
  }

  $build['menu_links'] = [
    '#type' => 'container',
    '#attributes' => [
      'class' => ['admin-list'],
    ],
    '#prefix' => '<dl class="admin-list">',
    '#suffix' => '</dl>',
    'items' => $items,
  ];

  return $build;
}

  /**
   * Restricted content listing.
   */
  public function restrictedContent(): array {

    $header = [
      $this->t('ID'),
      $this->t('Title'),
      $this->t('Content Type'),
      $this->t('Restricted Roles'),
      $this->t('Restriction Type'),
      $this->t('Administrator Bypass'),
      $this->t('Updated'),
      $this->t('Operations'),
    ];

    $node_storage = $this->entityTypeManagerService->getStorage('node');

    $query = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->exists('field_restrict_roles')
      ->sort('changed', 'DESC')
      ->pager(25);

    $nids = $query->execute();

    $rows = [];

    if (!empty($nids)) {

      $nodes = $node_storage->loadMultiple($nids);

      // Load user roles once.
      $roles = $this->entityTypeManagerService
        ->getStorage('user_role')
        ->loadMultiple();

      $role_labels = [];
      foreach ($roles as $role) {
        $role_labels[$role->id()] = $role->label();
      }

      // Load content types once.
      $node_types = $this->entityTypeManagerService
        ->getStorage('node_type')
        ->loadMultiple();

      foreach ($nodes as $node) {

        // Restricted roles.
        $restricted_roles = [];
        foreach ($node->get('field_restrict_roles') as $item) {
          $restricted_roles[] = $role_labels[$item->value] ?? $item->value;
        }

        $restriction_type = $node->get('field_restrict_action')->value === '404'
          ? $this->t('Page Not Found (404)')
          : $this->t('Access Denied (403)');

        $admin_bypass = $node->get('field_restrict_bypass_admin')->value
          ? $this->t('Yes')
          : $this->t('No');

        $operations = [];

        if ($node->access('update')) {
          $operations[] = Link::fromTextAndUrl(
            $this->t('Edit'),
            $node->toUrl('edit-form')
          )->toString();
        }

        if ($node->access('view')) {
          $operations[] = Link::fromTextAndUrl(
            $this->t('View'),
            $node->toUrl()
          )->toString();
        }

        $bundle = $node->bundle();

        $content_type = isset($node_types[$bundle])
          ? $node_types[$bundle]->label()
          : $bundle;

        $rows[] = [
          $node->id(),
          Link::fromTextAndUrl(
            $node->label(),
            $node->toUrl()
          ),
          $content_type,
          !empty($restricted_roles)
            ? implode(', ', $restricted_roles)
            : $this->t('None'),
          $restriction_type,
          $admin_bypass,
          $this->dateFormatter->format(
            $node->getChangedTime(),
            'short'
          ),
          [
            'data' => [
              '#markup' => implode(' | ', $operations),
            ],
          ],
        ];
      }
    }

    return [
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No restricted content found.'),
      ],
      'pager' => [
        '#type' => 'pager',
      ],
    ];
  }

}