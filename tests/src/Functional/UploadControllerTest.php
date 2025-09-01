<?php

namespace Drupal\Tests\image_api_upload\Functional;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\media\Entity\MediaType;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Functional test for the Image API Upload controller.
 *
 * @group image_api_upload
 */
class UploadControllerTest extends BrowserTestBase {

  protected static $modules = [
    'system',
    'user',
    'taxonomy',
    'field',
    'field_ui',
    'file',
    'media',
    'image_api_upload',
    'key_auth',
  ];

  /**
   * The default theme for the test.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * The admin user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * The API key for the admin user.
   *
   * @var string
   */
  protected $apiKey;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create and log in an admin user with upload permission.
    $this->adminUser = $this->drupalCreateUser([
      'upload image via api',
      'administer image api upload settings',
      'administer media',
      'administer taxonomy',
      'administer media fields',
      'use key authentication',
      'administer media',
      'administer media types',
      'view media',
    ]);
    $this->drupalLogin($this->adminUser);

    // Set an API key for the user.
    $this->apiKey = 'test-api-key-123';
    $this->adminUser->set('api_key', $this->apiKey);
    $this->adminUser->save();

    FieldStorageConfig::create([
      'field_name' => 'field_media_image',
      'entity_type' => 'media',
      'type' => 'image',
      'cardinality' => 1,
    ])->save();

    // Create a media type (bundle) for 'image'.
    $media_type = MediaType::create([
      'id' => 'image',
      'langcode' => 'en',
      'label' => 'Image',
      'description' => 'Use local images for reusable media.',
      'source' => 'image',
      'status' => TRUE,
      'queue_thumbnail_downloads' => FALSE,
      'new_revision' => TRUE,
      'source_configuration' => [
        'source_field' => 'field_media_image',
      ],
    ]);
    $media_type->save();

    // Set this so we can see the media content at media/{media_id}
    \Drupal::configFactory()->getEditable('media.settings')
      ->set('standalone_url', TRUE)
      ->save();

    // $this->drupalGet('admin/structure/media/manage/image');

    // Check the media form.
    $this->drupalGet('admin/structure/media/manage/image/fields');
    $this->drupalGet('media/add/image');

    // Add an image field to the media type.
    // FieldStorageConfig::create([
    //   'field_name' => 'field_media_image',
    //   'entity_type' => 'media',
    //   'type' => 'image',
    //   'cardinality' => 1,
    // ])->save();

    FieldConfig::create([
      'field_name' => 'field_media_image',
      'entity_type' => 'media',
      'bundle' => 'image',
      'label' => 'Media Image',
    ])->save();

    // Check the media form.
    $this->drupalGet('admin/structure/media/manage/image/fields');
    $this->drupalGet('media/add/image');

    // Add a credit field (text).
    FieldStorageConfig::create([
      'field_name' => 'field_credit',
      'entity_type' => 'media',
      'type' => 'text_long',
      'cardinality' => 1,
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_credit',
      'entity_type' => 'media',
      'bundle' => 'image',
      'label' => 'Credit',
    ])->save();

    // Check the media form.
    $this->drupalGet('admin/structure/media/manage/image/fields');
    $this->drupalGet('media/add/image');

    // Add a caption field (text_long).
    FieldStorageConfig::create([
      'field_name' => 'field_caption',
      'entity_type' => 'media',
      'type' => 'text_long',
      'cardinality' => 1,
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_caption',
      'entity_type' => 'media',
      'bundle' => 'image',
      'label' => 'Caption',
    ])->save();

    // Check the media form.
    $this->drupalGet('admin/structure/media/manage/image/fields');
    $this->drupalGet('media/add/image');

    // Add a taxonomy vocabulary and field.
    $vocab = Vocabulary::create([
      'vid' => 'media_tags',
      'name' => 'Media Tags',
    ]);
    $vocab->save();

    FieldStorageConfig::create([
      'field_name' => 'field_media_tags',
      'entity_type' => 'media',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
      'cardinality' => -1,
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_media_tags',
      'entity_type' => 'media',
      'bundle' => 'image',
      'label' => 'Media Tags',
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => [
            'media_tags' => 'media_tags',
          ],
        ],
      ],
    ])->save();

    // Set the module's runtime settings in state.
    \Drupal::state()->set('image_api_upload.settings', [
      'media_bundle' => 'image',
      'image_field' => 'field_media_image',
      'credit_field' => 'field_credit',
      'caption_field' => 'field_caption',
      'media_tags_field' => 'field_media_tags',
    ]);

    // Check the media form.
    $this->drupalGet('admin/structure/media/manage/image');

    $this->drupalGet('admin/structure/media/manage/image/fields');
    $this->drupalGet('media/add/image');
    $this->drupalGet('admin/config/media/image-api-upload');

    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();

    $display = EntityViewDisplay::load('media.image.default');
    if (!$display) {
      $display = EntityViewDisplay::create([
        'targetEntityType' => 'media',
        'bundle' => 'image',
        'mode' => 'default',
        'status' => TRUE,
        'id' => 'media.image.default',
      ]);
    }

    // Set all fields to be visible with a basic formatter.
    $display->setComponent('field_media_image', [
      'type' => 'image',
      'label' => 'above',
    ]);
    $display->setComponent('field_credit', [
      'type' => 'text_default',
      'label' => 'above',
    ]);
    $display->setComponent('field_caption', [
      'type' => 'text_default',
      'label' => 'above',
    ]);
    $display->setComponent('field_media_tags', [
      'type' => 'entity_reference_label',
      'label' => 'above',
    ]);

    $display->save();

    $form_display = EntityFormDisplay::load('media.image.default');
    if (!$form_display) {
      $form_display = EntityFormDisplay::create([
        'targetEntityType' => 'media',
        'bundle' => 'image',
        'mode' => 'default',
        'status' => TRUE,
        'id' => 'media.image.default',
      ]);
    }

    // Set all fields to be visible with a basic widget.
    $form_display->setComponent('field_media_image', [
      'type' => 'image_image',
      'weight' => 0,
    ]);
    $form_display->setComponent('field_credit', [
      'type' => 'text_textarea',
      'weight' => 1,
    ]);
    $form_display->setComponent('field_caption', [
      'type' => 'text_textarea',
      'weight' => 2,
    ]);
    $form_display->setComponent('field_media_tags', [
      'type' => 'entity_reference_autocomplete_tags',
      'weight' => 3,
    ]);

    $form_display->save();
  }

  /**
   * Test uploading an image via the API with key_auth.
   */
  public function testUploadImage() {
    // Create a minimal valid PNG image (1x1 pixel).
    $pngData = base64_decode(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2nK6kAAAAASUVORK5CYII='
    );
    $file_path = tempnam(sys_get_temp_dir(), 'img');
    file_put_contents($file_path, $pngData);

    // Prepare the multipart form data for file upload.
    $multipart = [
      [
        'name' => 'image',
        'contents' => fopen($file_path, 'r'),
        'filename' => 'test.png',
        'headers' => ['Content-Type' => 'image/png'],
      ],
      ['name' => 'name', 'contents' => 'Test Image'],
      ['name' => 'alt', 'contents' => 'Alt text'],
      ['name' => 'credit', 'contents' => 'Photographer'],
      ['name' => 'caption', 'contents' => 'A test caption.'],
      ['name' => 'upload_dir', 'contents' => 'test_uploads/foo/bar'],
      ['name' => 'media_tags', 'contents' => 'TagOne, TagTwo'],
    ];

    // Make the POST request using Guzzle with the API key in the header.
    $client = \Drupal::service('http_client');
    $response = $client->request('POST', $this->getAbsoluteUrl('/api/upload-image'), [
      'multipart' => $multipart,
      'http_errors' => FALSE,
      'headers' => [
        'Accept' => 'application/json',
        'api-key' => $this->apiKey,
      ],
    ]);

    if ($response->getStatusCode() !== 200) {
      print (string) $response->getBody();
    }

    $this->assertEquals(200, $response->getStatusCode());

    $json = json_decode($response->getBody(), TRUE);

    $this->assertEquals('Upload successful.', $json['message']);
    $this->assertNotEmpty($json['media_id']);
    $this->assertEquals('test_uploads/foo/bar', $json['directory']);

    $this->drupalGet('media/' . $json['media_id']);
    $this->drupalGet('media/' . $json['media_id'] . "/edit");

    // Check that the media entity was created.
    $media = \Drupal::entityTypeManager()->getStorage('media')->load($json['media_id']);
    $this->assertNotNull($media);
    $this->assertEquals('Test Image', $media->label());
    $this->assertEquals('Photographer', $media->get('field_credit')->value);
    $this->assertEquals('A test caption.', $media->get('field_caption')->value);

    // Check that tags were created and attached.
    $tags = $media->get('field_media_tags')->referencedEntities();
    $tag_names = array_map(function ($term) {
      return $term->label();
    }, $tags);
    $this->assertContains('TagOne', $tag_names);
    $this->assertContains('TagTwo', $tag_names);

    // $this->drupalGet('media/' . $json['media_id']);
  }

}
