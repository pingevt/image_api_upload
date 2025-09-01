<?php

namespace Drupal\image_api_upload\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;
use Drupal\file\FileRepositoryInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\State\StateInterface;

class UploadController extends ControllerBase {

  /**
   * The Container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  private $container;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * File Repository.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * File System.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Transliterator service.
   *
   * @var \Drupal\Component\Transliteration\TransliterationInterface
   */
  protected $transliterator;

  /**
   * Constructs a new UploadController.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\file\FileRepositoryInterface $fileRepository
   *   The file repository.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Component\Transliteration\TransliterationInterface $transliterator
   *   The transliteration service.
   */
  public function __construct(
    $container,
    EntityTypeManagerInterface $entityTypeManager,
    FileRepositoryInterface $fileRepository,
    FileSystemInterface $fileSystem,
    StateInterface $state,
    TransliterationInterface $transliterator
  ) {
    $this->container = $container;
    $this->entityTypeManager = $entityTypeManager;
    $this->fileRepository = $fileRepository;
    $this->fileSystem = $fileSystem;
    $this->state = $state;
    $this->transliterator = $transliterator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container,
      $container->get('entity_type.manager'),
      $container->get('file.repository'),
      $container->get('file_system'),
      $container->get('state'),
      $container->get('transliteration')
    );
  }

  /**
   * Upload an image.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function upload(Request $request): JsonResponse {
    /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $image */
    $image = $request->files->get('image');
    $upload_dir = $request->get('upload_dir', '');
    $name = $request->get('name', '');
    $alt = $request->get('alt', '');
    $credit = $request->get('credit', '');
    $caption = $request->get('caption', '');

    if (!$image || !$image->isValid()) {
      return new JsonResponse(['error' => 'Invalid image.'], 400);
    }

    // Validate and transliterate the upload directory.
    $upload_dir = trim($upload_dir);
    if ($upload_dir !== '') {
      // Remove leading/trailing slashes and transliterate.
      $upload_dir = trim($upload_dir, '/');
      $upload_dir = $this->transliterator->transliterate($upload_dir, 'en', '_');
      // Only allow safe characters (alphanumeric, underscore, dash, slash).
      $upload_dir = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $upload_dir);
      // Prevent directory traversal.
      $upload_dir = preg_replace('#\.\.+#', '', $upload_dir);
      // Fallback to 'my-uploads' if empty after cleaning.
      if ($upload_dir === '') {
        $upload_dir = 'my-uploads';
      }
    }
    else {
      $upload_dir = 'my-uploads';
    }

    // Save file to Drupal.
    $data = file_get_contents($image->getRealPath());
    $filename = $image->getClientOriginalName();
    $dest_dir = 'public://' . $upload_dir;
    $this->fileSystem->prepareDirectory($dest_dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $destination = $dest_dir . '/' . $filename;
    $file = $this->fileRepository->writeData($data, $destination, FileExists::Rename);

    if (!$file) {
      return new JsonResponse(['error' => 'Failed to save file.'], 500);
    }

    // Get bundle and field settings from config/state.
    $settings = $this->state->get('image_api_upload.settings', []);
    $bundle = $settings['media_bundle'] ?: 'image';
    $image_field = $settings['image_field'] ?: 'field_media_image';
    $credit_field = $settings['credit_field'] ?: 'field_credit';
    $caption_field = $settings['caption_field'];
    $taxonomy_field = $settings['media_tags_field'];

    // Prepare taxonomy terms if provided.
    $taxonomy_term_ids = [];
    if ($taxonomy_field && $request->get('media_tags')) {
      $tags_string = $request->get('media_tags');
      $tag_names = array_filter(array_map('trim', explode(',', $tags_string)));
      if (!empty($tag_names)) {
        // Get the target vocabulary from the field config.
        $field_config = $this->entityTypeManager
          ->getStorage('field_config')
          ->load('media.' . $bundle . '.' . $taxonomy_field);
        if ($field_config) {
          $settings = $field_config->getSettings();
          $vocabularies = $settings['handler_settings']['target_bundles'] ?? [];
          $vocabulary = reset($vocabularies);
          if ($vocabulary) {
            $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
            foreach ($tag_names as $tag_name) {
              // Try to load by name.
              $terms = $term_storage->loadByProperties([
                'name' => $tag_name,
                'vid' => $vocabulary,
              ]);
              if ($terms) {
                $term = reset($terms);
              }
              else {
                // Create the term if it doesn't exist.
                $term = $term_storage->create([
                  'name' => $tag_name,
                  'vid' => $vocabulary,
                ]);
                $term->save();
              }
              $taxonomy_term_ids[] = ['target_id' => $term->id()];
            }
          }
        }
      }
    }

    // Create media entity
    $media_values = [
      'bundle' => $bundle,
      'name' => $name ?: pathinfo($filename, PATHINFO_FILENAME),
      $image_field => [
        'target_id' => $file->id(),
        'alt' => $alt,
        'title' => $credit,
      ],
      $credit_field => $credit,
    ];

    if ($taxonomy_field && !empty($taxonomy_term_ids)) {
      $media_values[$taxonomy_field] = $taxonomy_term_ids;
    }

    if ($caption_field && $caption !== '') {
      $media_values[$caption_field] = $caption;
    }

    $media = Media::create($media_values);
    $media->save();

    return new JsonResponse([
      'message' => 'Upload successful.',
      'file_id' => $file->id(),
      'media_id' => $media->id(),
      'directory' => $upload_dir,
    ]);
  }

}
