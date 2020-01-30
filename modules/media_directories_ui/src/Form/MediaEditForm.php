<?php
namespace Drupal\media_directories_ui\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaTypeInterface;
use Drupal\media_directories_ui\Ajax\LoadDirectoryContent;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MediaEditForm
 *
 * Uses code and logic from core. We could try to integrate core directly,
 * but it might be too unstable in this stage.
 *
 * @package Drupal\media_directories_ui\Form
 */
class MediaEditForm extends FormBase {

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * MediaEditForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *
   * @return \Drupal\Core\Form\FormBase|\Drupal\media_directories_ui\Form\AddMediaFormBase
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_directories_media_edit_form';
  }

  /**
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return int|null
   */
  protected function getDirectory(FormStateInterface $form_state) {
    $directory_id = (int) $form_state->get('active_directory');

    if ($directory_id === MEDIA_DIRECTORY_ROOT) {
      $directory_id = NULL;
    }

    return $directory_id;
  }

  /**
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return mixed
   */
  protected function getTargetBundles(FormStateInterface $form_state) {
    $bundles = $form_state->get('target_bundles');

    return $bundles;
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#prefix'] = '<div id="media-library-add-form-wrapper" class="media-library-add-form-wrapper">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'media_library/style';

    // The form is posted via AJAX. When there are messages set during the
    // validation or submission of the form, the messages need to be shown to
    // the user.
    $form['status_messages'] = [
      '#type' => 'status_messages',
    ];

    $form['#attributes']['class'] = [
      'media-library-add-form',
      'js-media-library-add-form',
      'media-library-add-form--with-input',
    ];

    $form['active_directory'] = [
      '#type' => 'hidden',
      '#value' => $form_state->get('active_directory'),
    ];

    /** @var \Drupal\media\Entity\Media[] $selected_media */
    $selected_media = $form_state->get('media');

    $form['media'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'media-library-add-form__added-media',
        ],
        'aria-label' => $this->t('Added media items'),
        'role' => 'list',
        // Add the tabindex '-1' to allow the focus to be shifted to the added
        // media wrapper when items are added. We set focus to the container
        // because a media item does not necessarily have required fields and
        // we do not want to set focus to the remove button automatically.
        // @see ::updateFormCallback()
        'tabindex' => '-1',
      ],
    ];

    $form['media_items'] = [
      '#tree' => TRUE,
    ];

    foreach ($selected_media as $delta => $media) {
      // $media->set('directory', $this->directoryId);
      $form['media'][$delta] = $this->buildEntityFormElement($media, $form, $form_state, $delta);
      $form['media_items'][$delta] = [
        '#type' => 'hidden',
        '#value' => $media->id(),
      ];
    }

    $form['actions'] = $this->buildActions($form, $form_state);

    // Allow the current selection to be set in a hidden field so the selection
    // can be passed between different states of the form. This field is filled
    // via JavaScript so the default value should be empty.
    // @see Drupal.behaviors.MediaLibraryItemSelection
    $form['current_selection'] = [
      '#type' => 'hidden',
      '#default_value' => '',
      '#attributes' => [
        'class' => [
          'js-media-library-add-form-current-selection',
        ],
      ],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * Builds the sub-form for setting required fields on a new media item.
   *
   * @param \Drupal\media\MediaInterface $media
   *   A new, unsaved media item.
   * @param array $form
   *   The complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param int $delta
   *   The delta of the media item.
   *
   * @return array
   *   The element containing the required fields sub-form.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function buildEntityFormElement(MediaInterface $media, array $form, FormStateInterface $form_state, $delta) {
    $element = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'media-library-add-form__media',
        ],
        'aria-label' => $media->getName(),
        'role' => 'listitem',
        // Add the tabindex '-1' to allow the focus to be shifted to the next
        // media item when an item is removed. We set focus to the container
        // because a media item does not necessarily have required fields and we
        // do not want to set focus to the remove button automatically.
        // @see ::updateFormCallback()
        'tabindex' => '-1',
        // Add a data attribute containing the delta to allow us to easily shift
        // the focus to a specific media item.
        // @see ::updateFormCallback()
        'data-media-library-added-delta' => $delta,
      ],
      'preview' => [
        '#type' => 'container',
        '#weight' => 10,
        '#attributes' => [
          'class' => [
            'media-library-add-form__preview',
          ],
        ],
      ],
      'fields' => [
        '#type' => 'container',
        '#weight' => 20,
        '#attributes' => [
          'class' => [
            'media-library-add-form__fields',
          ],
        ],
        // The '#parents' are set here because the entity form display needs it
        // to build the entity form fields.
        '#parents' => ['media', $delta, 'fields'],
      ],
    ];
    // @todo Make the image style configurable in
    //   https://www.drupal.org/node/2988223
    $source = $media->getSource();
    $plugin_definition = $source->getPluginDefinition();
    if ($thumbnail_uri = $source->getMetadata($media, $plugin_definition['thumbnail_uri_metadata_attribute'])) {
      $element['preview']['thumbnail'] = [
        '#theme' => 'image_style',
        '#style_name' => 'media_library',
        '#uri' => $thumbnail_uri,
      ];
    }

    $form_display = EntityFormDisplay::collectRenderDisplay($media, 'media_library');
    // When the name is not added to the form as an editable field, output
    // the name as a fixed element to confirm the right file was uploaded.
    if (!$form_display->getComponent('name')) {
      $element['fields']['name'] = [
        '#type' => 'item',
        '#title' => $this->t('Name'),
        '#markup' => $media->getName(),
      ];
    }
    $form_display->buildForm($media, $element['fields'], $form_state);

    // Make the language field un-editable.
    if (isset($element['fields']['langcode'])) {
      $languages = $media->getTranslationLanguages();
      $translations = [];
      foreach ($languages as $langcode => $language) {
        $translations[$langcode] = $media->id();
      }
      // We allow altering the (source-)language.
      // Media library dialogs actually just hide the field ..
      if (count($translations) > 1) {
        $element['fields']['langcode']['#disabled'] = TRUE;
        $element['fields']['langcode']['#suffix'] = '<div>' . $this->t('In this dialog you are only allowed to change the source language of medias having no translation yet.') . '</div>';
      }
    }

    // We hide the preview of the uploaded file in the image widget with CSS.
    // But provide a link to the full media edit page.
    // @todo Improve hiding file widget elements in
    //   https://www.drupal.org/project/drupal/issues/2987921
    $bundle = $this->entityTypeManager->getStorage('media_type')->load($media->bundle());
    $source_field_name = $this->getSourceFieldName($bundle);
    if (isset($element['fields'][$source_field_name])) {
      $element['fields'][$source_field_name]['#attributes']['class'][] = 'media-library-add-form__source-field';
      $element['fields'][$source_field_name]['#prefix'] = '<a href="/media/' . $media->id() . '/edit" target="_blank">' . $this->t('Edit in new tab (fe. for changing the image)') . '</a>';
    }
    // The revision log field is currently not configurable from the form
    // display, so hide it by changing the access.
    // @todo Make the revision_log_message field configurable in
    //   https://www.drupal.org/project/drupal/issues/2696555
    if (isset($element['fields']['revision_log_message'])) {
      $element['fields']['revision_log_message']['#access'] = FALSE;
    }
    return $element;
  }

  /**
   * Returns the name of the source field for a media type.
   *
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The media type to get the source field name for.
   *
   * @return string
   *   The name of the media type's source field.
   */
  protected function getSourceFieldName(MediaTypeInterface $media_type) {
    return $media_type->getSource()
      ->getSourceFieldDefinition($media_type)
      ->getName();
  }


  /**
   * Returns an array of supported actions for the form.
   *
   * @param array $form
   *   The complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   An actions element containing the actions of the form.
   */
  protected function buildActions(array $form, FormStateInterface $form_state) {
    return [
      '#type' => 'actions',
      'save' => [
        '#type' => 'submit',
        '#button_type' => 'primary',
        '#value' => $this->t('Save'),
        '#ajax' => [
          'callback' => '::saveMedia',
          'wrapper' => 'media-library-add-form-wrapper',
        ],
      ],
    ];
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public function saveMedia(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $added_media = $form_state->get('media');

    foreach ($added_media as $delta => $media) {
      EntityFormDisplay::collectRenderDisplay($media, 'media_library')
        ->extractFormValues($media, $form['media'][$delta]['fields'], $form_state);
      //$this->prepareMediaEntityForSave($media);
      $media->save();
    }

    $response->addCommand(new LoadDirectoryContent());
    $response->addCommand(new CloseModalDialogCommand());

    return $response;
  }


  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Nothing to do here.
  }
}
