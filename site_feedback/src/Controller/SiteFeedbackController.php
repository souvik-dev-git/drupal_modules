<?php

namespace Drupal\site_feedback\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\site_feedback\Form\SiteFeedbackForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\site_feedback\Entity\SiteFeedback;

/**
 * Controller for Website Experience Feedback.
 */
class SiteFeedbackController extends ControllerBase {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs the controller.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(RendererInterface $renderer, RequestStack $request_stack) {
    $this->renderer = $renderer;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer'),
      $container->get('request_stack')
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
      $request->headers->get('X-Site-Feedback-Request') !== 'modal'
    ) {
      throw new AccessDeniedHttpException('Access Denied');
    }

    $form = $this->formBuilder()->getForm(
      SiteFeedbackForm::class
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
      'site_issue',
      'documentation',
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
    * Validate uploaded files against the current session.
    *
    * Uploads are temporary until they are attached to a feedback
    * submission. The session list prevents a visitor from attaching or
    * deleting a file uploaded by another visitor.
    */
    if (!empty($attachment_fids)) {
      $file_storage = $this->entityTypeManager()->getStorage('file');
      $session_fids = $this->getUploadedFileIds();
      $invalid_fids = [];

      foreach ($attachment_fids as $fid) {
        $file = $file_storage->load($fid);

        if (!$file || !isset($session_fids[$fid])) {
          $invalid_fids[] = $fid;
          continue;
        }

        if (strpos($file->getFileUri(), 'public://site_feedback/') !== 0) {
          $invalid_fids[] = $fid;
        }
      }

      if (!empty($invalid_fids)) {
        return new JsonResponse([
          'success' => FALSE,
          'message' => $this->t('One or more uploaded images are no longer available.'),
        ], 422);
      }
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
      $feedback_entity = SiteFeedback::create([
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

      ]);

      $feedback_entity->save();

      // Submitted attachments are now referenced by the feedback entity.
      foreach ($attachment_fids as $fid) {
        $attachment = $this->entityTypeManager()->getStorage('file')->load($fid);
        if ($attachment) {
          $attachment->setPermanent();
          $attachment->save();
        }
      }

      $this->forgetUploadedFiles($attachment_fids);

      $feedback_reference = $feedback_entity->getFeedbackReference();

      return new JsonResponse([
        'success' => TRUE,
        'message' => 'Your feedback has been submitted successfully.',
        'feedback_reference' => $feedback_reference,
      ]);
    }
    catch (\Throwable $e) {
      \Drupal::logger('site_feedback')->error(
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
      ], 500);
    }
  }


  private function calculatePriority(string $feedback_type, int $rating, string $feedback): string {

    $feedback_lower = strtolower($feedback);
    $critical_keywords = \Drupal::config(
      'site_feedback.settings'
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

      case 'site_issue':
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

      case 'documentation':
        if ($rating <= 2) {
          return '2';
        }

        return '3';

      case 'positive':
        return '3';

      default:
        return '2';
    }
  }

  private function calculateImpact(string $feedback_type, int $rating, string $feedback): string {

    $feedback_lower = strtolower($feedback);
    $critical_keywords = \Drupal::config(
      'site_feedback.settings'
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

    if ($feedback_type === 'site_issue') {
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

    if ($feedback_type === 'documentation') {
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
      'site_feedback.settings'
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

    
    if ($feedback_type === 'site_issue') {
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

    if ($feedback_type === 'documentation') {
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

  public function upload(Request $request) {
    $uploadedFile = $request->files->get('file');

    if (!$uploadedFile) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t('No image was uploaded.'),
      ], 400);
    }

    if (!$uploadedFile->isValid()) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t('The image upload failed.'),
      ], 400);
    }

    $allowedExtensions = ['png', 'jpg', 'jpeg', 'webp'];
    $extension = strtolower((string) $uploadedFile->getClientOriginalExtension());

    if (!in_array($extension, $allowedExtensions, TRUE)) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t('Only PNG, JPG, JPEG and WebP images are allowed.'),
      ], 400);
    }

    $maxSize = 5 * 1024 * 1024;
    if ((int) $uploadedFile->getSize() > $maxSize) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t('The maximum allowed image size is 5 MB.'),
      ], 400);
    }

    $allowedMimeTypes = ['image/png', 'image/jpeg', 'image/webp'];
    $mimeType = (string) $uploadedFile->getMimeType();

    if (!in_array($mimeType, $allowedMimeTypes, TRUE)) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t('The uploaded file is not a valid image.'),
      ], 400);
    }

    /*
     * Verify the actual image content and reject excessively large pixel
     * dimensions. This is stronger than trusting the extension/MIME type.
     */
    $imageInfo = @getimagesize($uploadedFile->getRealPath());
    if ($imageInfo === FALSE || empty($imageInfo[0]) || empty($imageInfo[1])) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t('The uploaded file is not a valid image.'),
      ], 400);
    }

    $maxPixels = 25000000;
    if (((int) $imageInfo[0] * (int) $imageInfo[1]) > $maxPixels) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t('The image dimensions are too large.'),
      ], 400);
    }

    $directory = 'public://site_feedback';
    $file_system = $this->getFileSystem();

    if (!$file_system->prepareDirectory(
      $directory,
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS
    )) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t('The upload directory could not be created.'),
      ], 500);
    }

    $contents = file_get_contents($uploadedFile->getRealPath());
    if ($contents === FALSE) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t('Unable to read the uploaded image.'),
      ], 500);
    }

    $originalName = $uploadedFile->getClientOriginalName();
    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '-', $originalName);
    $safeName = trim((string) $safeName, '.-');
    if ($safeName === '') {
      $safeName = 'site-feedback-image.' . $extension;
    }

    try {
      $file = $this->getFileRepository()->writeData(
        $contents,
        $directory . '/' . $safeName,
        FileSystemInterface::EXISTS_RENAME
      );

      if (!$file) {
        throw new \RuntimeException('File repository did not return a file entity.');
      }

      $file->setOwnerId($this->currentUser()->id());
      $file->setTemporary();
      $file->save();

      $this->rememberUploadedFile((int) $file->id());

      $file_url = $this->getFileUrlGenerator()->generateAbsoluteString($file->getFileUri());

      return new JsonResponse([
        'success' => TRUE,
        'fid' => (int) $file->id(),
        'filename' => $file->getFilename(),
        'filesize' => (int) $file->getSize(),
        'url' => $file_url,
        'uri' => $file->getFileUri(),
      ]);
    }
    catch (\Throwable $e) {
      \Drupal::logger('site_feedback')->error(
        'Feedback image upload failed: @message',
        ['@message' => $e->getMessage()]
      );

      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t('The image could not be uploaded.'),
      ], 500);
    }
  }

  /**
   * Delete an uploaded image.
   */
  public function delete($fid) {
    $fid = (int) $fid;
    $file = File::load($fid);

    if (!$file) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t('The image could not be found.'),
      ], 404);
    }

    if (!$this->ownsUploadedFile($fid)) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t('You are not authorized to delete this image.'),
      ], 403);
    }

    if (strpos($file->getFileUri(), 'public://site_feedback/') !== 0) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t('Invalid image location.'),
      ], 403);
    }

    $file->delete();
    $this->forgetUploadedFile($fid);

    return new JsonResponse([
      'success' => TRUE,
      'message' => $this->t('Image deleted successfully.'),
    ]);
  }

  /**
   * Returns the IDs of files uploaded by the current session.
   *
   * @return array<int, int>
   *   File IDs keyed by file ID.
   */
  protected function getUploadedFileIds(): array {
    $session = $this->requestStack->getCurrentRequest()->getSession();
    return array_map('intval', (array) $session->get('site_feedback.uploaded_fids', []));
  }

  /**
   * Records an uploaded file in the current session.
   */
  protected function rememberUploadedFile(int $fid): void {
    $fids = $this->getUploadedFileIds();
    $fids[$fid] = $fid;
    $this->requestStack->getCurrentRequest()->getSession()->set('site_feedback.uploaded_fids', $fids);
  }

  /**
   * Removes an uploaded file from the current session.
   */
  protected function forgetUploadedFile(int $fid): void {
    $fids = $this->getUploadedFileIds();
    unset($fids[$fid]);
    $this->requestStack->getCurrentRequest()->getSession()->set('site_feedback.uploaded_fids', $fids);
  }

  /**
   * Checks whether a file belongs to the current upload session.
   */
  protected function ownsUploadedFile(int $fid): bool {
    $fids = $this->getUploadedFileIds();
    return isset($fids[$fid]);
  }

  /**
   * Removes submitted files from the current upload session.
   */
  protected function forgetUploadedFiles(array $fids): void {
    $stored = $this->getUploadedFileIds();
    foreach ($fids as $fid) {
      unset($stored[(int) $fid]);
    }
    $this->requestStack->getCurrentRequest()->getSession()->set('site_feedback.uploaded_fids', $stored);
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
  public function view($site_feedback) {
    $feedback = \Drupal::entityTypeManager()
      ->getStorage('site_feedback')
      ->load($site_feedback);

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
          'class' => ['site-feedback-attachment-item'],
        ],
        'image' => [
          '#theme' => 'image',
          '#uri' => $file_uri,
          '#alt' => $file->getFilename(),
          '#attributes' => [
            'class' => ['site-feedback-attachment-image'],
          ],
        ],
        'filename' => [
          '#markup' => '<div class="site-feedback-attachment-name">' .
            htmlspecialchars($file->getFilename()) .
            '</div>',
        ],
      ];
    }

    $build = [
      '#type' => 'container',

      '#attached' => [
        'library' => [
          'site_feedback/feedback_admin',
        ],
      ],

      '#attributes' => [
        'class' => ['site-feedback-detail'],
      ],

      // Header.
      'header' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['site-feedback-detail-header'],
        ],

        'header_content' => [
          '#markup' => '
            <div class="site-feedback-header-reference">
              ' . htmlspecialchars(
                $feedback->get('feedback_reference')->value
              ) . '
            </div>

            <h1 class="site-feedback-header-title">
              ' . htmlspecialchars(
                $feedback->get('title')->value
              ) . '
            </h1>

            <div class="site-feedback-header-subtitle">
              Website Experience Feedback
            </div>
          ',
        ],
      ],

      // Metadata.
      'metadata' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['site-feedback-metadata-grid'],
        ],

        'type' => [
          '#markup' => '
            <div class="site-feedback-meta-card">
              <div class="site-feedback-meta-label">
                Type
              </div>
              <div class="site-feedback-meta-value">
                ' . htmlspecialchars($feedback_type_label) . '
              </div>
            </div>
          ',
        ],

        'rating' => [
          '#markup' => '
            <div class="site-feedback-meta-card">
              <div class="site-feedback-meta-label">
                Rating
              </div>
              <div class="site-feedback-meta-value site-feedback-rating">
                <span class="site-feedback-rating-stars">' .
                  $rating_stars .
                '</span>
                <span class="site-feedback-rating-number">' .
                  $rating . '/5
                </span>
              </div>
            </div>
          ',
        ],

        'status' => [
          '#markup' => '
            <div class="site-feedback-meta-card">
              <div class="site-feedback-meta-label">
                Status
              </div>
              <div class="site-feedback-meta-value">
                <span class="site-feedback-status site-feedback-status-' .
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
            <div class="site-feedback-meta-card">
              <div class="site-feedback-meta-label">
                Priority
              </div>
              <div class="site-feedback-meta-value">
                <span class="site-feedback-priority site-feedback-priority-' .
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
            <div class="site-feedback-meta-card">
              <div class="site-feedback-meta-label">
                Impact
              </div>
              <div class="site-feedback-meta-value">
                <span class="site-feedback-impact site-feedback-impact-' .
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
            <div class="site-feedback-meta-card">
              <div class="site-feedback-meta-label">
                Urgency
              </div>
              <div class="site-feedback-meta-value">
                <span class="site-feedback-urgency site-feedback-urgency-' .
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
          <div class="site-feedback-section site-feedback-source-section">

            <div class="site-feedback-section-header">
              <div>
                <h2>Source Page</h2>
                <span>Page from which the feedback was submitted</span>
              </div>
            </div>

            <div class="site-feedback-source-url">
              ' .
              ($feedback->get('source_url')->value
                ? '<a href="' .
                  htmlspecialchars($feedback->get('source_url')->value) .
                  '" target="_blank" rel="noopener noreferrer">' .
                  htmlspecialchars($feedback->get('source_url')->value) .
                  '</a>'
                : '<span class="site-feedback-empty">Not available</span>'
              ) .
            '</div>

          </div>
        ',
      ],

      // Feedback.
      'feedback' => [
        '#markup' => '
          <div class="site-feedback-section">

            <div class="site-feedback-section-header">
              <div>
                <h2>Feedback</h2>
                <span>Submitted feedback details</span>
              </div>
            </div>

            <div class="site-feedback-message">
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
    // Add the Attachments section only when attachments exist.
    if (!empty($attachments)) {
      $build['attachments'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'site-feedback-section',
            'site-feedback-attachments',
          ],
        ],

        'header' => [
          '#markup' => '
            <div class="site-feedback-section-header">
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
            'class' => ['site-feedback-attachment-grid'],
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
      ->getStorage('site_feedback_note');

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
              'site-feedback-admin-note',
            ],
          ],

          'marker' => [
            '#type' => 'container',
            '#attributes' => [
              'class' => [
                'site-feedback-admin-note-marker',
              ],
            ],
            '#markup' => '<span></span>',
          ],

          'content' => [
            '#type' => 'container',
            '#attributes' => [
              'class' => [
                'site-feedback-admin-note-content',
              ],
            ],

            'header' => [
              '#markup' => '
                <div class="site-feedback-admin-note-header">
                  <div class="site-feedback-admin-note-author">
                    <span class="site-feedback-admin-note-avatar">
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

                    <span class="site-feedback-admin-note-author-name">
                      ' .
                      htmlspecialchars(
                        $author_name,
                        ENT_QUOTES,
                        'UTF-8'
                      ) .
                    '</span>
                  </div>

                  <div class="site-feedback-admin-note-date">
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
                <div class="site-feedback-admin-note-text">
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
            'site-feedback-section',
            'site-feedback-admin-notes',
          ],
        ],

        'header' => [
          '#markup' => '
            <div class="site-feedback-section-header">
              <div>
                <h2>Admin Notes</h2>
                <span>
                  Internal notes maintained by administrators
                </span>
              </div>

              <div class="site-feedback-admin-note-count">
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
              'site-feedback-admin-notes-timeline',
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
          'site-feedback-detail-actions',
        ],
      ],
    ];

    $build['actions']['change_status'] = [
      '#type' => 'link',
      '#title' => $this->t('Update Ticket'),
      '#url' => Url::fromRoute(
        'entity.site_feedback.change_status',
        [
          'site_feedback' => $feedback->id(),
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
  public function feedbackTitle($site_feedback) {
    $feedback = \Drupal::entityTypeManager()
      ->getStorage('site_feedback')
      ->load($site_feedback);

    if (!$feedback) {
      return $this->t('Feedback Details');
    }

    return $this->t('Feedback @reference', [
      '@reference' => $feedback->get('feedback_reference')->value,
    ]);
  }

}