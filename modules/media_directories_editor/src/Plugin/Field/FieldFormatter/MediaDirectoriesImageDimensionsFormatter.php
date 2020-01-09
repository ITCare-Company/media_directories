<?php

namespace Drupal\media_directories_editor\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\image\ImageStyleStorageInterface;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Render\RendererInterface;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Plugin implementation of the 'media_directories_editor_thumbnail' formatter.
 *
 * @FieldFormatter(
 *   id = "media_directories_image_dimensions",
 *   label = @Translation("Image with dimensions"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class MediaDirectoriesImageDimensionsFormatter extends ImageFormatter {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs an MediaThumbnailFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\image\ImageStyleStorageInterface $image_style_storage
   *   The image style entity storage handler.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, AccountInterface $current_user, ImageStyleStorageInterface $image_style_storage, RendererInterface $renderer) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $current_user, $image_style_storage);
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('current_user'),
      $container->get('entity_type.manager')->getStorage('image_style'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'dimensions' => [
          'image_width' => '',
          'image_height' => '',
        ],
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   *
   * This has to be overridden because FileFormatterBase expects $item to be
   * of type \Drupal\file\Plugin\Field\FieldType\FileItem and calls
   * isDisplayed() which is not in FieldItemInterface.
   */
  protected function needsEntityLoad(EntityReferenceItem $item) {
    return !$item->hasNewEntity();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    if ($this->viewMode === '_entity_embed') {
      $storage = $form_state->getStorage();
      /** @var \Drupal\media\Entity\Media $entity */
      $entity = $storage['entity'];
      $element['#attached']['library'][] = 'media_directories_editor/image-resize';
      $element['image_style']['#access'] = FALSE;
      $element['image_link']['#access'] = FALSE;

      $element['dimensions'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Image size'),
        '#description' => $this->t('Original image size: @widthx@height', [
          '@width' => $entity->get('thumbnail')->width,
          '@height' => $entity->get('thumbnail')->height,
          ]),
        '#attributes' => [
          'class' => ['media-directories-editor--dimensions'],
        ],
      ];

      $dimensions = $this->getSetting('dimensions');

      $img_width = empty($dimensions['image_width']) ? $entity->get('thumbnail')->width : $dimensions['image_width'];
      $img_height = empty($dimensions['image_height']) ? $entity->get('thumbnail')->height : $dimensions['image_height'];

      $element['dimensions']['image_width'] = [
        '#title' => t('Width'),
        '#type' => 'textfield',
        '#size' => 4,
        '#default_value' => $img_width,
        '#attributes' => [
          'class' => ['media-directories-editor--image-width'],
        ],
      ];

      $element['dimensions']['image_height'] = [
        '#title' => t('Height'),
        '#type' => 'textfield',
        '#size' => 4,
        '#default_value' => $img_height,
        '#attributes' => [
          'class' => ['media-directories-editor--image-height'],
        ],
      ];

      $element['dimensions']['controls'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['media-directories-editor--controls']
        ],
        'reset' => [
          '#type' => 'html_tag',
          '#tag' => 'a',
          '#attributes' => [
            'class' => ['media-directories-editor--reset', 'button'],
            'data-width' => $entity->get('thumbnail')->width,
            'data-height' => $entity->get('thumbnail')->height,
          ],
          '#value' => $this->t('Reset'),
        ],
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $media_items = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($media_items)) {
      return $elements;
    }

    /** @var \Drupal\media\MediaInterface[] $media_items */
    foreach ($media_items as $delta => $media) {
      /** @var \Drupal\file\Entity\File $file */
      $file = $media->get('thumbnail')->entity;

      $elements[$delta] = [
        '#theme' => 'image',
        '#attributes' => [
          'width' => $this->getSetting('dimensions')['image_width'],
          'height' => $this->getSetting('dimensions')['image_height'],
          'class' => [],
        ],
        '#uri' => file_create_url($file->getFileUri()),
      ];

      // Add cacheability of each item in the field.
      $this->renderer->addCacheableDependency($elements[$delta], $media);
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    // This formatter is only available for entity types that reference
    // media items.
    return ($field_definition->getFieldStorageDefinition()->getSetting('target_type') == 'media');
  }

  /**
   * Get the URL for the media thumbnail.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media item.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that the field belongs to.
   *
   * @return \Drupal\Core\Url|null
   *   The URL object for the media item or null if we don't want to add
   *   a link.
   */
  protected function getMediaThumbnailUrl(MediaInterface $media, EntityInterface $entity) {
    $url = NULL;
    $image_link_setting = $this->getSetting('image_link');
    // Check if the formatter involves a link.
    if ($image_link_setting == 'content') {
      if (!$entity->isNew()) {
        $url = $entity->toUrl();
      }
    }
    elseif ($image_link_setting === 'media') {
      $url = $media->toUrl();
    }
    return $url;
  }

}
