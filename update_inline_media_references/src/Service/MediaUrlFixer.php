<?php

// src/Service/MediaUrlFixer.php

namespace Drupal\update_inline_media_references\Service;

use Drupal\Component\Utility\Html;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\Entity\File;

class MediaUrlFixer {

  /**
   * File URL generator.
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * Constructor.
   */
  public function __construct(
    FileUrlGeneratorInterface $file_url_generator
  ) {

    $this->fileUrlGenerator = $file_url_generator;

  }

  /**
   * Rewrite inline media URLs.
   */
  public function rewriteHtml(
    string $html,
    array $allowed_extensions = []
  ): string {

    /**
     * Normalize extensions.
     */
    $allowed_extensions = array_map(
      'strtolower',
      $allowed_extensions
    );

    $dom = Html::load($html);

    $xpath = new \DOMXPath($dom);

    /**
     * Supported elements.
     */
    $elements = [
      [
        'tag' => 'img',
        'attribute' => 'src',
      ],
      [
        'tag' => 'a',
        'attribute' => 'href',
      ],
      [
        'tag' => 'source',
        'attribute' => 'src',
      ],
      [
        'tag' => 'video',
        'attribute' => 'src',
      ],
      [
        'tag' => 'iframe',
        'attribute' => 'src',
      ],
    ];

    foreach ($elements as $element_config) {

      $tag = $element_config['tag'];

      $attribute = $element_config['attribute'];

      $nodes = $xpath->query("//{$tag}");

      foreach ($nodes as $node) {

        $url = $node->getAttribute(
          $attribute
        );

        if (empty($url)) {
          continue;
        }

        /**
         * Extract filename.
         */
        $path = parse_url(
          $url,
          PHP_URL_PATH
        );

        $filename = urldecode(
          basename($path)
        );

        $filename = str_replace(
          '+',
          ' ',
          $filename
        );

        if (empty($filename)) {
          continue;
        }

        /**
         * Validate extension.
         */
        $extension = strtolower(
          pathinfo(
            $filename,
            PATHINFO_EXTENSION
          )
        );

        if (
          empty($extension) ||
          !in_array(
            $extension,
            $allowed_extensions
          )
        ) {
          continue;
        }

        /**
         * Find Drupal file entity.
         */
        $fids = \Drupal::entityQuery('file')
          ->condition(
            'filename',
            $filename
          )
          ->accessCheck(FALSE)
          ->range(0, 1)
          ->execute();

        if (empty($fids)) {
          continue;
        }

        $fid = reset($fids);

        $file = File::load($fid);

        if (!$file) {
          continue;
        }

        /**
         * Generate new URL.
         */
        $new_url = $this->fileUrlGenerator
          ->generateString(
            $file->getFileUri()
          );

        /**
         * Update URL.
         */
        $node->setAttribute(
          $attribute,
          $new_url
        );

        /**
         * Add alt text for images.
         */
        if ($tag === 'img') {

          $alt = pathinfo(
            $filename,
            PATHINFO_FILENAME
          );

          $node->setAttribute(
            'alt',
            $alt
          );
        }
      }
    }

    /**
     * Return cleaned HTML.
     */
    $body = $dom
      ->getElementsByTagName('body')
      ->item(0);

    $output = '';

    if ($body) {

      foreach ($body->childNodes as $child) {

        $output .= $dom->saveHTML($child);

      }
    }

    return $output;
  }

}