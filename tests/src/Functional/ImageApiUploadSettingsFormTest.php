<?php

namespace Drupal\Tests\image_api_upload\Functional;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\MediaType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\User;

/**
 * Tests the Image API Upload settings form.
 *
 * @group image_api_upload
 */
class ImageApiUploadSettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'taxonomy',
    'field',
    'media',
    'image_api_upload',
  ];

  /**
   * The default theme for the test.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to administer site configuration.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  protected $mediaVocab;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create and log in an admin user.
    $this->adminUser = $this->drupalCreateUser([
      'administer site configuration',
      'administer media',
      'administer taxonomy',
      'administer image api upload settings',
    ]);
    $this->drupalLogin($this->adminUser);

    // Create a media type (bundle) for 'image'.
    $media_type = MediaType::create([
      'id' => 'image',
      'label' => 'Image',
      'source' => 'image',
    ]);
    $media_type->save();

    // Add an image field to the media type.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_media_image',
      'entity_type' => 'media',
      'type' => 'image',
      'cardinality' => 1,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_name' => 'field_media_image',
      'entity_type' => 'media',
      'bundle' => 'image',
      'label' => 'Media Image',
    ]);
    $field->save();

    // Add a credit field (text).
    $credit_storage = FieldStorageConfig::create([
      'field_name' => 'field_credit',
      'entity_type' => 'media',
      'type' => 'string',
      'cardinality' => 1,
    ]);
    $credit_storage->save();

    $credit_field = FieldConfig::create([
      'field_name' => 'field_credit',
      'entity_type' => 'media',
      'bundle' => 'image',
      'label' => 'Credit',
    ]);
    $credit_field->save();

    // Add a caption field (text).
    $caption_storage = FieldStorageConfig::create([
      'field_name' => 'field_caption',
      'entity_type' => 'media',
      'type' => 'string',
      'cardinality' => 1,
    ]);
    $caption_storage->save();

    $caption_field = FieldConfig::create([
      'field_name' => 'field_caption',
      'entity_type' => 'media',
      'bundle' => 'image',
      'label' => 'Caption',
    ]);
    $caption_field->save();

    // Optionally, add a taxonomy field if you want to test that as well.
    $this->mediaVocab = Vocabulary::create([
      'vid' => 'tags',
      'description' => 'Tags for media',
      'name' => 'Tags',
    ]);
    $this->mediaVocab->save();

    // Add taxonomy field to Media.
    $tags_storage = FieldStorageConfig::create([
      'field_name' => 'field_media_tags',
      'entity_type' => 'media',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ]);
    $tags_storage->save();

    $tags_field = FieldConfig::create([
      'field_name' => 'field_media_tags',
      'entity_type' => 'media',
      'bundle' => 'image',
      'label' => 'Media Tags',
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => [
            $this->mediaVocab->id() => $this->mediaVocab->id(),
          ],
        ],
      ],
    ]);
    $tags_field->save();

  }

  /**
   * Test the settings form displays and saves values.
   */
  public function testSettingsFormBuildAndSubmit() {
    // Visit the settings form.
    $this->drupalGet('/admin/config/media/image-api-upload');
    $this->assertSession()->statusCodeEquals(200);

    // Check that the form fields exist.
    $this->assertSession()->fieldExists('media_bundle');
    $this->assertSession()->fieldExists('image_field');
    $this->assertSession()->fieldExists('credit_field');
    $this->assertSession()->fieldExists('media_tags_field');
    $this->assertSession()->fieldExists('caption_field');

    // Submit the form with test values.
    $edit = [
      'media_bundle' => 'image',
      // Use actual field machine names present in your test site.
      'image_field' => 'field_media_image',
      'credit_field' => 'field_credit',
      'caption_field' => 'field_caption',
      'media_tags_field' => 'field_media_tags',
    ];
    $this->submitForm($edit, 'Save settings');

    // Check for confirmation message.
    $this->assertSession()->pageTextContains('Settings have been saved');

    // Check that state was saved.
    $state = \Drupal::state()->get('image_api_upload.settings');
    $this->assertEquals('image', $state['media_bundle']);
    $this->assertEquals('field_media_image', $state['image_field']);
    $this->assertEquals('field_credit', $state['credit_field']);
    $this->assertEquals('field_caption', $state['caption_field']);
    $this->assertEquals('field_media_tags', $state['media_tags_field']);
  }

}
