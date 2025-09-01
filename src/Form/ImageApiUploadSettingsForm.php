<?php

namespace Drupal\image_api_upload\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure runtime settings for Image API Upload.
 */
class ImageApiUploadSettingsForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->state = $container->get('state');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'image_api_upload_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // $form = parent::buildForm($form, $form_state);
    $form = [];

    // Get all media fields for all media bundles.
    $fields = [];
    $credit_fields = [];
    $taxonomy_fields = [];
    $caption_fields = [];
    $bundles = $this->entityTypeManager->getStorage('media_type')->loadMultiple();
    $bundle_options = [];
    foreach ($bundles as $bundle_id => $bundle) {
      $bundle_options[$bundle_id] = $bundle->label();
    }

    // Use state instead of config.
    $settings = $this->state->get('image_api_upload.settings', []);

    if (isset($bundles['image'])) {
      $fields_info = $this->entityTypeManager
        ->getStorage('field_storage_config')
        ->loadByProperties(['entity_type' => 'media']);
      foreach ($fields_info as $field) {
        $field_name = $field->getName();
        $label = $field->label() . " ($field_name)";
        // File/image fields.
        if ($field->getType() === 'image' || $field->getType() === 'file') {
          $fields[$field_name] = $label;
        }
        // Text fields for credit.
        if (in_array($field->getType(), ['string', 'text', 'text_long'])) {
          $credit_fields[$field_name] = $label;
        }
        // Taxonomy reference fields.
        if ($field->getType() === 'entity_reference' && $field->getSetting('target_type') === 'taxonomy_term') {
          $taxonomy_fields[$field_name] = $label;
        }
        // Text fields for caption.
        if (in_array($field->getType(), ['string', 'text', 'text_long'])) {
          $caption_fields[$field_name] = $label;
        }
      }
    }

    $form['media_bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Media bundle'),
      '#options' => $bundle_options,
      '#default_value' => $settings['media_bundle'] ?? 'image',
      '#required' => TRUE,
      '#description' => $this->t('Select the media bundle to use for uploads.'),
    ];

    $form['image_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Image file field'),
      '#options' => $fields,
      '#default_value' => $settings['image_field'] ?? 'field_media_image',
      '#required' => FALSE,
      '#description' => $this->t('Select the image or file field to use for uploads.'),
    ];

    $form['credit_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Credit field'),
      '#options' => $credit_fields,
      '#default_value' => $settings['credit_field'] ?? 'field_credit',
      '#required' => TRUE,
      '#description' => $this->t('Select the field to use for image credit.'),
    ];

    $form['caption_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Caption field'),
      '#options' => ['' => $this->t('- None -')] + $caption_fields,
      '#default_value' => $settings['caption_field'] ?? '',
      '#required' => FALSE,
      '#description' => $this->t('Optionally select a field to use for image caption.'),
    ];

    // Add the optional taxonomy field for media tags.
    $form['media_tags_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Media Tags'),
      '#options' => ['' => $this->t('- None -')] + $taxonomy_fields,
      '#default_value' => $settings['media_tags_field'] ?? '',
      '#required' => FALSE,
      '#description' => $this->t('Optionally select a taxonomy reference field to tag uploaded media.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save settings'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $settings = [
      'image_field' => $form_state->getValue('image_field'),
      'credit_field' => $form_state->getValue('credit_field'),
      'taxonomy_field' => $form_state->getValue('taxonomy_field'),
      'media_bundle' => $form_state->getValue('media_bundle'),
      'media_tags_field' => $form_state->getValue('media_tags_field'),
      'caption_field' => $form_state->getValue('caption_field'),
    ];
    $this->state->set('image_api_upload.settings', $settings);

    $this->messenger()->addStatus($this->t('Settings have been saved (runtime only, not config).'));
  }

}
