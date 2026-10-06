<?php

namespace Drupal\website_feedback\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\website_feedback\Form\WebsiteFeedbackForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\website_feedback\Entity\WebsiteFeedback;

/**
 * Controller for Developer Portal Experience Feedback.
 */
class WebsiteFeedbackController extends ControllerBase {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs the controller.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(RendererInterface $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer')
    );
  }

  /**
   * Display the developer feedback form.
   */
  public function form(Request $request) {

    /*
    * Only allow requests coming from the feedback modal.
    */
    if (
      $request->headers->get('X-Website-Feedback-Request') !== 'modal'
    ) {
      throw new AccessDeniedHttpException('Access Denied');
    }

    $form = $this->formBuilder()->getForm(
      WebsiteFeedbackForm::class
    );

    $html = $this->renderer->renderRoot($form);

    return new Response($html);
  }

  public function submit(Request $request) {
    if ($request->isMethod('POST') === FALSE) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Invalid request method.',
      ], 405);
    }

    $values = $request->request->all();

    $errors = [];

    /*
    * Title.
    */
    $title = isset($values['title'])
      ? trim((string) $values['title'])
      : '';

    if ($title === '') {
      $errors['title'] = 'Title field is required.';
    }

    /*
    * Rating.
    */
    $rating = isset($values['rating'])
      ? (string) $values['rating']
      : '';

    if ($rating === '') {
      $errors['rating'] = 'Please select an overall experience rating.';
    }
    elseif (!in_array((int) $rating, [1, 2, 3, 4, 5], TRUE)) {
      $errors['rating'] = 'Please select a valid rating.';
    }

    /*
    * Feedback type.
    */
    $feedback_type = isset($values['feedback_type'])
      ? (string) $values['feedback_type']
      : '';

    $allowed_feedback_types = [
      'improvement',
      'website_issue',
      'positive',
    ];

    if ($feedback_type === '') {
      $errors['feedback_type'] = 'Please select a feedback type.';
    }
    elseif (!in_array($feedback_type, $allowed_feedback_types, TRUE)) {
      $errors['feedback_type'] = 'Please select a valid feedback type.';
    }

    /*
    * Feedback.
    */
    $feedback = isset($values['feedback'])
      ? trim((string) $values['feedback'])
      : '';

    if ($feedback === '') {
      $errors['feedback'] = 'Please enter your feedback.';
    }

    /*
    * Image / screenshot attachments.
    *
    * The existing image-upload.js stores uploaded file IDs
    * in the uploaded_image_fids field.
    */
    $attachment_fids = [];

    if (!empty($values['uploaded_image_fids'])) {
      $attachment_fids = array_filter(
        array_map(
          'intval',
          explode(',', (string) $values['uploaded_image_fids'])
        )
      );
    }

    /*
    * Remove duplicate file IDs.
    */
    $attachment_fids = array_values(array_unique($attachment_fids));


    /*
    * Validate uploaded files before saving them to the entity.
    */
    if (!empty($attachment_fids)) {
      $file_storage = \Drupal::entityTypeManager()->getStorage('file');
      $valid_attachment_fids = [];

      foreach ($attachment_fids as $fid) {
        $file = $file_storage->load($fid);

        if (!$file) {
          continue;
        }

        /*
        * Only allow files from the website feedback directory.
        */
        if (strpos($file->getFileUri(), 'public://website_feedback/') !== 0) {
          continue;
        }

        $valid_attachment_fids[] = $fid;
      }

      $attachment_fids = $valid_attachment_fids;
    }

    /*
    * Return validation errors before creating the entity.
    */
    if (!empty($errors)) {
      return new JsonResponse([
        'success' => FALSE,
        'errors' => $errors,
      ], 422);
    }
    /*
    * Automatically capture the source page URL.
    */
    $source_url = trim(
      (string) $request->headers->get('referer')
    );
    try {
      $feedback_entity = WebsiteFeedback::create([
        'title' => $title,
        'rating' => $rating,
        'feedback_type' => $feedback_type,
        'feedback' => $feedback,
        'status' => 'new',

        'priority' => $this->calculatePriority(
          $feedback_type,
          (int) $rating,
          $feedback
        ),

        'impact' => $this->calculateImpact(
          $feedback_type,
          (int) $rating,
          $feedback
        ),

        'urgency' => $this->calculateUrgency(
          $feedback_type,
          (int) $rating,
          $feedback
        ),

        'uid' => $this->currentUser()->id(),
        'source_url' => $source_url,
        'attachments' => $attachment_fids,

        'servicenow_integration_status' => 'pending',
      ]);

      $feedback_entity->save();

      /*
      * ServiceNow integration.
      *
      * Positive feedback does not create an INC.
      *
      * Website Issue and Improvement feedback
      * create a mock ServiceNow incident.
      */
      if ($feedback_type === 'positive') {

        /*
        * Positive feedback does not require a ServiceNow ticket.
        */
        $feedback_entity->set(
          'servicenow_integration_status',
          'not_required'
        );

        $feedback_entity->set(
          'servicenow_ticket_number',
          NULL
        );

        $feedback_entity->set(
          'servicenow_ticket_id',
          NULL
        );

      }
      else {

        try {

          /*
          * Get the Mock ServiceNow service.
          */
          $service_now = \Drupal::service(
            'website_feedback.servicenow_ticket'
          );

          /*
          * Create the mock ServiceNow incident.
          */
          $service_now_result = $service_now->createTicket(
            $feedback_entity
          );

          if (
            !empty($service_now_result['success'])
            && $service_now_result['success'] === TRUE
          ) {

            /*
            * Store ServiceNow Ticket Number.
            */
            $feedback_entity->set(
              'servicenow_ticket_number',
              $service_now_result['ticket_number']
            );

            /*
            * Store ServiceNow Ticket ID.
            */
            $feedback_entity->set(
              'servicenow_ticket_id',
              $service_now_result['ticket_id']
            );

            /*
            * Mark the integration as successfully created.
            */
            $feedback_entity->set(
              'servicenow_integration_status',
              'created'
            );

          }
          else {

            /*
            * ServiceNow returned an unsuccessful response.
            */
            $feedback_entity->set(
              'servicenow_integration_status',
              'failed'
            );

          }

        }
        catch (\Throwable $e) {

          /*
          * Log the ServiceNow integration failure.
          */
          \Drupal::logger(
            'website_feedback'
          )->error(
            'Mock ServiceNow ticket creation failed for feedback @id: @message',
            [
              '@id' => $feedback_entity->id(),
              '@message' => $e->getMessage(),
            ]
          );

          /*
          * Do not lose the Drupal feedback if ServiceNow fails.
          */
          $feedback_entity->set(
            'servicenow_integration_status',
            'failed'
          );

        }

      }

      /*
      * Save the ServiceNow information/status.
      */
      $feedback_entity->save();

      $feedback_reference = $feedback_entity->getFeedbackReference();

      $service_now_ticket_number = $feedback_entity->get('servicenow_ticket_number')->value;

      return new JsonResponse([
        'success' => TRUE,
        'message' => 'Your feedback has been submitted successfully.',
        'feedback_reference' => $feedback_reference,
        'servicenow_ticket_number' => $service_now_ticket_number,
      ]);
    }
    catch (\Throwable $e) {
      \Drupal::logger('website_feedback')->error(
        'Feedback entity save failed: @message in @file on line @line',
        [
          '@message' => $e->getMessage(),
          '@file' => $e->getFile(),
          '@line' => $e->getLine(),
        ]
      );

      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Feedback could not be saved.',
        'error' => $e->getMessage(),
      ], 500);
    }
  }


  private function calculatePriority(string $feedback_type, int $rating, string $feedback): string {

    $feedback_lower = strtolower($feedback);
    $critical_keywords = \Drupal::config(
      'website_feedback.settings'
    )->get('critical_keywords') ?: [];

    foreach ($critical_keywords as $keyword) {
      $keyword = strtolower(trim($keyword));

      if (
        $keyword !== ''
        && str_contains($feedback_lower, $keyword)
      ) {
        return '1';
      }
    }

    switch ($feedback_type) {

      case 'website_issue':
        if ($rating <= 2) {
          return '1';
        }

        if ($rating === 3) {
          return '2';
        }

        return '3';

      case 'improvement':
        if ($rating <= 2) {
          return '1';
        }

        return '2';

      case 'positive':
        return '3';

      default:
        return '2';
    }
  }

  private function calculateImpact(string $feedback_type, int $rating, string $feedback): string {

    $feedback_lower = strtolower($feedback);
    $critical_keywords = \Drupal::config(
      'website_feedback.settings'
    )->get('critical_keywords') ?: [];

    foreach ($critical_keywords as $keyword) {
      $keyword = strtolower(trim($keyword));

      if (
        $keyword !== ''
        && str_contains($feedback_lower, $keyword)
      ) {
        return '1';
      }
    }

    if ($feedback_type === 'website_issue') {
      if ($rating <= 2) {
        return '1';
      }

      if ($rating === 3) {
        return '2';
      }

      return '3';
    }

    if ($feedback_type === 'improvement') {
      if ($rating <= 2) {
        return '2';
      }

      return '3';
    }

    if ($feedback_type === 'positive') {
      return '3';
    }

    return '2';
  }

  private function calculateUrgency(string $feedback_type, int $rating, string $feedback): string {
    $feedback_lower = strtolower($feedback);

    $critical_keywords = \Drupal::config(
      'website_feedback.settings'
    )->get('critical_keywords') ?: [];

    foreach ($critical_keywords as $keyword) {
      $keyword = strtolower(trim($keyword));

      if (
        $keyword !== ''
        && str_contains($feedback_lower, $keyword)
      ) {
        return '1';
      }
    }

    
    if ($feedback_type === 'website_issue') {
      if ($rating <= 2) {
        return '1';
      }

      if ($rating === 3) {
        return '2';
      }

      return '3';
    }

    
    if ($feedback_type === 'improvement') {
      if ($rating <= 2) {
        return '1';
      }

      return '2';
    }

    if ($feedback_type === 'positive') {
      return '3';
    }

    return '2';
  }

  public function upload(Request $request) {

    /*
    * Get uploaded file.
    */
    $uploadedFile = $request->files->get('file');

    if (!$uploadedFile) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t('No image was uploaded.'),
      ], 400);
    }

    /*
    * Check upload error.
    */
    if (!$uploadedFile->isValid()) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t('The image upload failed.'),
      ], 400);
    }

    /*
    * Allowed extensions.
    */
    $allowedExtensions = [
      'png',
      'jpg',
      'jpeg',
      'webp',
    ];

    $extension = strtolower(
      $uploadedFile->getClientOriginalExtension()
    );

    if (!in_array($extension, $allowedExtensions, TRUE)) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t(
          'Only PNG, JPG, JPEG and WebP images are allowed.'
        ),
      ], 400);
    }

    /*
    * Maximum file size: 5 MB.
    */
    $maxSize = 5 * 1024 * 1024;

    if ($uploadedFile->getSize() > $maxSize) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t(
          'The maximum allowed image size is 5 MB.'
        ),
      ], 400);
    }

    $image_info = @getimagesize($uploadedFile->getRealPath());

    if ($image_info === FALSE) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t(
          'The uploaded file is not a valid image.'
        ),
      ], 400);
    }

    $allowedMimeTypes = [
      'image/png',
      'image/jpeg',
      'image/webp',
    ];

    if (
      empty($image_info['mime'])
      || !in_array($image_info['mime'], $allowedMimeTypes, TRUE)
    ) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t(
          'Only PNG, JPG, JPEG and WebP images are allowed.'
        ),
      ], 400);
    }

    /*
    * Upload directory.
    */
    $directory = 'public://website_feedback';

    $file_system = $this->getFileSystem();

    /*
    * Create directory if it does not exist.
    */
    if (!$file_system->prepareDirectory(
      $directory,
      FileSystemInterface::CREATE_DIRECTORY |
      FileSystemInterface::MODIFY_PERMISSIONS
    )) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t(
          'The upload directory could not be created.'
        ),
      ], 500);
    }

    /*
    * Read uploaded file contents.
    */
    $contents = file_get_contents(
      $uploadedFile->getRealPath()
    );

    if ($contents === FALSE) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t(
          'Unable to read the uploaded image.'
        ),
      ], 500);
    }

    /*
    * Generate a safe filename.
    *
    * Screenshot files will normally arrive as:
    * website-feedback-screenshot.png
    */
    $originalName = $uploadedFile->getClientOriginalName();

    $safeName = preg_replace(
      '/[^a-zA-Z0-9._-]/',
      '-',
      $originalName
    );

    /*
    * Save the file.
    */
    $file = $this->getFileRepository()->writeData(
      $contents,
      $directory . '/' . $safeName,
      FileSystemInterface::EXISTS_RENAME
    );

    if (!$file) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t(
          'The image could not be uploaded.'
        ),
      ], 500);
    }

    /*
    * Make the file permanent.
    */
    $file->setPermanent();
    $file->save();

    /*
    * Generate absolute public URL.
    */
    $file_url = $this->getFileUrlGenerator()
      ->generateAbsoluteString(
        $file->getFileUri()
      );

    return new JsonResponse([
      'success' => TRUE,
      'fid' => (int) $file->id(),
      'filename' => $file->getFilename(),
      'filesize' => (int) $file->getSize(),
      'url' => $file_url,
      'uri' => $file->getFileUri(),
    ]);
  }

  /**
   * Delete an uploaded image.
   */
  public function delete($fid) {

    /*
     * Load file entity.
     */
    $file = File::load($fid);

    if (!$file) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t(
          'The image could not be found.'
        ),
      ], 404);
    }

    /*
     * Only allow deletion of files from
     * public://website_feedback/.
     */
    $uri = $file->getFileUri();

    if (strpos(
      $uri,
      'public://website_feedback/'
    ) !== 0) {

      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t(
          'Invalid image location.'
        ),
      ], 403);
    }

    /*
     * Delete file entity and physical file.
     */
    $file->delete();

    return new JsonResponse([
      'success' => TRUE,
      'message' => $this->t(
        'Image deleted successfully.'
      ),
    ]);
  }

  /**
   * Get file system service.
   */
  protected function getFileSystem() {
    return \Drupal::service('file_system');
  }

  /**
   * Get file repository service.
   */
  protected function getFileRepository() {
    return \Drupal::service('file.repository');
  }

  /**
   * Get file URL generator service.
   */
  protected function getFileUrlGenerator() {
    return \Drupal::service('file_url_generator');
  }

  /**
  * Displays a feedback entity.
  */
  public function view($website_feedback) {
    $feedback = \Drupal::entityTypeManager()
      ->getStorage('website_feedback')
      ->load($website_feedback);

    if (!$feedback) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $feedback_type = $feedback->get('feedback_type')->value;

    $feedback_type_label = $feedback
      ->getFieldDefinition('feedback_type')
      ->getSetting('allowed_values')[$feedback_type] ?? $feedback_type;

    $status = $feedback->get('status')->value;

    $status_label = $feedback
      ->getFieldDefinition('status')
      ->getSetting('allowed_values')[$status] ?? $status;

    $priority = $feedback->get('priority')->value;

    $priority_label = $feedback
      ->getFieldDefinition('priority')
      ->getSetting('allowed_values')[$priority] ?? $priority;

    $impact = $feedback->get('impact')->value;

    $impact_label = $feedback
      ->getFieldDefinition('impact')
      ->getSetting('allowed_values')[$impact] ?? $impact;

    $urgency = $feedback->get('urgency')->value;

    $urgency_label = $feedback
      ->getFieldDefinition('urgency')
      ->getSetting('allowed_values')[$urgency] ?? $urgency;

    $rating = (int) $feedback->get('rating')->value;

    $service_now_ticket_number = $feedback
      ->get('servicenow_ticket_number')
      ->value;

    $service_now_status = $feedback
      ->get('servicenow_integration_status')
      ->value;

    $service_now_status_labels = [
      'pending' => $this->t('Pending'),
      'created' => $this->t('Created'),
      'failed' => $this->t('Failed'),
      'not_required' => $this->t('Not Required'),
    ];

    $service_now_status_label =
      $service_now_status_labels[$service_now_status]
      ?? $service_now_status;

    $status_class = 'website-feedback-servicenow-' . ($service_now_status ?: 'pending');

    // Build rating stars using plain text characters.
    $rating_stars = '';

    for ($i = 1; $i <= 5; $i++) {
      $rating_stars .= $i <= $rating ? '★' : '☆';
    }

    // Build attachment images.
    $attachments = [];

    foreach ($feedback->get('attachments') as $attachment) {
      $file = $attachment->entity;

      if (!$file) {
        continue;
      }

      $file_uri = $file->getFileUri();

      $attachments[] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['website-feedback-attachment-item'],
        ],
        'image' => [
          '#theme' => 'image',
          '#uri' => $file_uri,
          '#alt' => $file->getFilename(),
          '#attributes' => [
            'class' => ['website-feedback-attachment-image'],
          ],
        ],
        'filename' => [
          '#markup' => '<div class="website-feedback-attachment-name">' .
            htmlspecialchars($file->getFilename()) .
            '</div>',
        ],
      ];
    }

    $build = [
      '#type' => 'container',

      '#attached' => [
        'library' => [
          'website_feedback/feedback_admin',
        ],
      ],

      '#attributes' => [
        'class' => ['website-feedback-detail'],
      ],

      // Header.
      'header' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['website-feedback-detail-header'],
        ],

        'header_content' => [
          '#markup' => '
            <div class="website-feedback-header-reference">
              ' . htmlspecialchars(
                $feedback->get('feedback_reference')->value
              ) . '
            </div>

            <h1 class="website-feedback-header-title">
              ' . htmlspecialchars(
                $feedback->get('title')->value
              ) . '
            </h1>

            <div class="website-feedback-header-subtitle">
              Website Experience Feedback
            </div>
          ',
        ],
      ],

      // Metadata.
      'metadata' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['website-feedback-metadata-grid'],
        ],

        'type' => [
          '#markup' => '
            <div class="website-feedback-meta-card">
              <div class="website-feedback-meta-label">
                Type
              </div>
              <div class="website-feedback-meta-value">
                ' . htmlspecialchars($feedback_type_label) . '
              </div>
            </div>
          ',
        ],

        'rating' => [
          '#markup' => '
            <div class="website-feedback-meta-card">
              <div class="website-feedback-meta-label">
                Rating
              </div>
              <div class="website-feedback-meta-value website-feedback-rating">
                <span class="website-feedback-rating-stars">' .
                  $rating_stars .
                '</span>
                <span class="website-feedback-rating-number">' .
                  $rating . '/5
                </span>
              </div>
            </div>
          ',
        ],

        'status' => [
          '#markup' => '
            <div class="website-feedback-meta-card">
              <div class="website-feedback-meta-label">
                Status
              </div>
              <div class="website-feedback-meta-value">
                <span class="website-feedback-status website-feedback-status-' .
                  htmlspecialchars($status) .
                '">
                  ' . htmlspecialchars($status_label) . '
                </span>
              </div>
            </div>
          ',
        ],

        'priority' => [
          '#markup' => '
            <div class="website-feedback-meta-card">
              <div class="website-feedback-meta-label">
                Priority
              </div>
              <div class="website-feedback-meta-value">
                <span class="website-feedback-priority website-feedback-priority-' .
                  htmlspecialchars($priority) .
                '">
                  ' . htmlspecialchars($priority_label) . '
                </span>
              </div>
            </div>
          ',
        ],

        'impact' => [
          '#markup' => '
            <div class="website-feedback-meta-card">
              <div class="website-feedback-meta-label">
                Impact
              </div>
              <div class="website-feedback-meta-value">
                <span class="website-feedback-impact website-feedback-impact-' .
                  htmlspecialchars($impact) .
                '">
                  ' . htmlspecialchars($impact_label) . '
                </span>
              </div>
            </div>
          ',
        ],

        'urgency' => [
          '#markup' => '
            <div class="website-feedback-meta-card">
              <div class="website-feedback-meta-label">
                Urgency
              </div>
              <div class="website-feedback-meta-value">
                <span class="website-feedback-urgency website-feedback-urgency-' .
                  htmlspecialchars($urgency) .
                '">
                  ' . htmlspecialchars($urgency_label) . '
                </span>
              </div>
            </div>
          ',
        ],
      ],

      // Source URL.
      'source_url' => [
        '#markup' => '
          <div class="website-feedback-section website-feedback-source-section">

            <div class="website-feedback-section-header">
              <div>
                <h2>Source Page</h2>
                <span>Page from which the feedback was submitted</span>
              </div>
            </div>

            <div class="website-feedback-source-url">
              ' .
              ($feedback->get('source_url')->value
                ? '<a href="' .
                  htmlspecialchars($feedback->get('source_url')->value) .
                  '" target="_blank" rel="noopener noreferrer">' .
                  htmlspecialchars($feedback->get('source_url')->value) .
                  '</a>'
                : '<span class="website-feedback-empty">Not available</span>'
              ) .
            '</div>

          </div>
        ',
      ],

      // Feedback.
      'feedback' => [
        '#markup' => '
          <div class="website-feedback-section">

            <div class="website-feedback-section-header">
              <div>
                <h2>Feedback</h2>
                <span>Submitted feedback details</span>
              </div>
            </div>

            <div class="website-feedback-message">
              ' .
              nl2br(
                htmlspecialchars(
                  $feedback->get('feedback')->value
                )
              ) .
            '</div>

          </div>
        ',
      ],
    ];
    $build['metadata']['servicenow_ticket'] = [
      '#markup' => '
        <div class="website-feedback-meta-card">
          <div class="website-feedback-meta-label">
            ServiceNow Ticket
          </div>
          <div class="website-feedback-meta-value">
            ' .
            htmlspecialchars(
              $service_now_ticket_number ?: 'Not Applicable',
              ENT_QUOTES,
              'UTF-8'
            ) .
          '
          </div>
        </div>
      ',
    ];
    $build['metadata']['servicenow_status'] = [
      '#markup' => '
        <div class="website-feedback-meta-card">
          <div class="website-feedback-meta-label">
            ServiceNow Integration
          </div>
          <div class="website-feedback-meta-value">
            <span class="website-feedback-servicenow-status ' .
            htmlspecialchars(
              $status_class,
              ENT_QUOTES,
              'UTF-8'
            ) .
            '">
              ' .
              htmlspecialchars(
                $service_now_status_label,
                ENT_QUOTES,
                'UTF-8'
              ) .
            '
            </span>
          </div>
        </div>
      ',
    ];

    // Add the Attachments section only when attachments exist.
    if (!empty($attachments)) {
      $build['attachments'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'website-feedback-section',
            'website-feedback-attachments',
          ],
        ],

        'header' => [
          '#markup' => '
            <div class="website-feedback-section-header">
              <div>
                <h2>Attachments</h2>
                <span>
                  Images and screenshots submitted with this feedback
                </span>
              </div>
            </div>
          ',
        ],

        'images' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['website-feedback-attachment-grid'],
          ],
          'items' => $attachments,
        ],
      ];
    }

    /*
    * Load all admin notes for this feedback.
    *
    * Notes are stored as separate entities, allowing
    * one feedback to have multiple admin notes.
    */
    $admin_note_storage = \Drupal::entityTypeManager()
      ->getStorage('website_feedback_note');

    $admin_note_ids = $admin_note_storage
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('feedback_id', $feedback->id())
      ->sort('created', 'DESC')
      ->execute();

    $admin_notes = $admin_note_storage->loadMultiple(
      $admin_note_ids
    );

    /*
    * Build the Admin Notes section.
    */
    if (!empty($admin_notes)) {
      $notes = [];

      foreach ($admin_notes as $admin_note) {
        $author = $admin_note->get('uid')->entity;

        $author_name = $author
          ? $author->getDisplayName()
          : $this->t('Administrator');

        $created = $admin_note->get('created')->value;

        $notes[] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => [
              'website-feedback-admin-note',
            ],
          ],

          'marker' => [
            '#type' => 'container',
            '#attributes' => [
              'class' => [
                'website-feedback-admin-note-marker',
              ],
            ],
            '#markup' => '<span></span>',
          ],

          'content' => [
            '#type' => 'container',
            '#attributes' => [
              'class' => [
                'website-feedback-admin-note-content',
              ],
            ],

            'header' => [
              '#markup' => '
                <div class="website-feedback-admin-note-header">
                  <div class="website-feedback-admin-note-author">
                    <span class="website-feedback-admin-note-avatar">
                      ' .
                      htmlspecialchars(
                        strtoupper(
                          mb_substr(
                            $author_name,
                            0,
                            1
                          )
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                      ) .
                    '</span>

                    <span class="website-feedback-admin-note-author-name">
                      ' .
                      htmlspecialchars(
                        $author_name,
                        ENT_QUOTES,
                        'UTF-8'
                      ) .
                    '</span>
                  </div>

                  <div class="website-feedback-admin-note-date">
                    ' .
                    \Drupal::service('date.formatter')->format(
                      $created,
                      'short'
                    ) .
                    '
                  </div>
                </div>
              ',
            ],

            'text' => [
              '#markup' => '
                <div class="website-feedback-admin-note-text">
                  ' .
                  nl2br(
                    htmlspecialchars(
                      $admin_note->get('note')->value,
                      ENT_QUOTES,
                      'UTF-8'
                    )
                  ) .
                  '
                </div>
              ',
            ],
          ],
        ];
      }

      $build['admin_notes'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'website-feedback-section',
            'website-feedback-admin-notes',
          ],
        ],

        'header' => [
          '#markup' => '
            <div class="website-feedback-section-header">
              <div>
                <h2>Admin Notes</h2>
                <span>
                  Internal notes maintained by administrators
                </span>
              </div>

              <div class="website-feedback-admin-note-count">
                ' .
                count($admin_notes) .
                ' ' .
                (count($admin_notes) === 1 ? 'note' : 'notes') .
                '
              </div>
            </div>
          ',
        ],

        'timeline' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => [
              'website-feedback-admin-notes-timeline',
            ],
          ],
          'items' => $notes,
        ],
      ];
    }

    /*
    * Administrative actions.
    *
    * Change Status is intentionally available only from
    * the individual feedback detail page.
    */
    $build['actions'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'website-feedback-detail-actions',
        ],
      ],
    ];

    $build['actions']['change_status'] = [
      '#type' => 'link',
      '#title' => $this->t('Update Ticket'),
      '#url' => Url::fromRoute(
        'entity.website_feedback.change_status',
        [
          'website_feedback' => $feedback->id(),
        ]
      ),
      '#attributes' => [
        'class' => [
          'button',
          'button--primary',
        ],
      ],
    ];

    return $build;
  }

  /**
   * Returns the title for the feedback detail page.
   */
  public function feedbackTitle($website_feedback) {
    $feedback = \Drupal::entityTypeManager()
      ->getStorage('website_feedback')
      ->load($website_feedback);

    if (!$feedback) {
      return $this->t('Feedback Details');
    }

    return $this->t('Feedback @reference', [
      '@reference' => $feedback->get('feedback_reference')->value,
    ]);
  }

}