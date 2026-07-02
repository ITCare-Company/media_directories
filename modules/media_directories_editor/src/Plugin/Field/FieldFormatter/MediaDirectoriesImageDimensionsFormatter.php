<?php

namespace Drupal\media_directories_editor\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Drupal\media\Plugin\Field\FieldFormatter\MediaThumbnailFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
class MediaDirectoriesImageDimensionsFormatter extends MediaThumbnailFormatter {

  /**
   * The configuration factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    // Build the instance via the parent formatter instead of overriding the
    // constructor. Core changed the ImageFormatter/MediaThumbnailFormatter
    // constructor signature in Drupal 11.4 (typed, promoted properties plus a
    // new ImageDerivativeUtilities argument), so mirroring it here would break
    // on either older or newer core. Letting the parent construct the object
    // keeps this formatter compatible across all supported core versions; we
    // only inject the extra service this class needs on top.
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->configFactory = $container->get('config.factory');
    return $instance;
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
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    if ($this->viewMode === '_entity_embed') {
      $storage = $form_state->getStorage();
      /** @var \Drupal\media\Entity\Media $entity */
      $entity = $storage['entity'];
      $element['#attached']['library'][] = 'media_directories_editor/image-resize';
      $element['image_link']['#access'] = FALSE;

      $config = $this->configFactory->get('media_directories_editor.settings');
      $styles = $element['image_style']['#options'];
      $selected_styles = $config->get('embed_dialog.image_styles');

      if (!empty($selected_styles)) {
        $styles = array_intersect_key($styles, $selected_styles);
      }

      $image_style_options[(string) $this->t('Pre-defined styles')] = $styles;

      $element['image_style']['#options'] = $image_style_options;
      $element['image_style']['#empty_option'] = $this->t('Custom dimensions');
      $element['image_style']['#description'] = $this->t('Choose from pre-defined image styles or set custom dimensions.');

      $thumbnail_width = $entity->get('thumbnail')->width;
      $thumbnail_height = $entity->get('thumbnail')->height;
      $has_dimensions = !empty($thumbnail_width) && !empty($thumbnail_height);

      if ($has_dimensions) {
        $description = $this->t('Original image size: @widthx@height', [
          '@width' => $thumbnail_width,
          '@height' => $thumbnail_height,
        ]);
      }
      else {
        $description = $this->t('Original image dimensions are not available (e.g. SVG).');
      }

      $element['dimensions'] = [
        '#type' => 'details',
        '#title' => $this->t('Image size'),
        '#description' => $description,
        '#open' => TRUE,
        '#attributes' => [
          'class' => ['media-directories-editor--dimensions'],
        ],
        '#states' => [
          'visible' => [
            ':input[name="attributes[data-entity-embed-display-settings][image_style]"]' => ['value' => ''],
          ],
        ],
      ];

      $dimensions = $this->getSetting('dimensions');

      $img_width = empty($dimensions['image_width']) ? ($thumbnail_width ?: '') : $dimensions['image_width'];
      $img_height = empty($dimensions['image_height']) ? ($thumbnail_height ?: '') : $dimensions['image_height'];

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
          'class' => ['media-directories-editor--controls'],
        ],
        'reset' => [
          '#type' => 'html_tag',
          '#tag' => 'a',
          '#attributes' => [
            'class' => ['media-directories-editor--reset', 'button'],
            'data-width' => $thumbnail_width ?: '',
            'data-height' => $thumbnail_height ?: '',
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

    if ($this->getSetting('image_style')) {
      return parent::viewElements($items, $langcode);
    }

    /** @var \Drupal\media\MediaInterface[] $media_items */
    foreach ($media_items as $delta => $media) {
      /** @var \Drupal\file\Entity\File $file */
      $file = $media->get('thumbnail')->entity;

      if (!$file instanceof FileInterface) {
        continue; // Skip this media item if there's no file.
      }

      $alt = $media->get('thumbnail')->alt;

      $elements[$delta] = [
        '#theme' => 'image',
        '#attributes' => [
          'width' => $this->getSetting('dimensions')['image_width'],
          'height' => $this->getSetting('dimensions')['image_height'],
          'alt' => $alt,
          'class' => [],
        ],
        '#uri' => $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri()),
      ];

      // Add cacheability of each item in the field.
      $this->renderer->addCacheableDependency($elements[$delta], $media);
    }

    return $elements;
  }

}
