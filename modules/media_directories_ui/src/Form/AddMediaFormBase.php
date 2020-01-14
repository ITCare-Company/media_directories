<?php
namespace Drupal\media_directories_ui\Form;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Token;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\file\Entity\File;
use Drupal\media\MediaInterface;
use Drupal\media\MediaTypeInterface;
use Drupal\media_directories_ui\Ajax\RefreshDirectoryTree;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AddMediaFormBase
 *
 * Uses code and logic from core. We could try to integrate core directly,
 * but it might be too unstable in this stage.
 *
 * @package Drupal\media_directories_ui\Form
 */
abstract class AddMediaFormBase extends FormBase {

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
   * The token replacement instance.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * AddMediaFormBase constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   * @param \Drupal\Core\Utility\Token $token
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user, Token $token) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->token = $token;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *
   * @return \Drupal\Core\Form\FormBase|\Drupal\media_directories_ui\Form\AddMediaFormBase
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('token')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_directories_add_form';
  }

  /**
   * Get the media type from the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return \Drupal\media\MediaTypeInterface
   *   The media type.
   *
   * @throws \InvalidArgumentException
   *   If the selected media type does not exist.
   */
  protected function getMediaType(FormStateInterface $form_state) {
    if (!$form_state->get('media_type')) {
      throw new \InvalidArgumentException("The media type does not exist.");
    }

    return $form_state->get('media_type');
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
   * Determines the URI for a file field.
   *
   * @param array $settings
   *   The array of field settings.
   *
   * @return string
   *   An un-sanitized file directory URI with tokens replaced. The result of
   *   the token replacement is then converted to plain text and returned.
   */
  protected function getUploadLocation(array $settings) {
    $destination = trim($settings['file_directory'], '/');

     // Replace tokens. As the tokens might contain HTML we convert it to plain
     // text.
     $destination = PlainTextOutput::renderFromHtml($this->token->replace($destination, []));
     return $settings['uri_scheme'] . '://' . $destination;
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
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#prefix'] = '<div id="media-library-add-form-wrapper" class="media-library-add-form-wrapper">';
    $form['#suffix'] = '</div>';
    // For 8.7.
    $form['#attached']['library'][] = 'media_library/style';
    // For 8.8, style moved to seven theme. It should work with 8.7.
    $form['#attached']['library'][] = 'seven/media_library';

    // The form is posted via AJAX. When there are messages set during the
    // validation or submission of the form, the messages need to be shown to
    // the user.
    $form['status_messages'] = [
      '#type' => 'status_messages',
    ];

    $form['#attributes']['class'] = [
      'media-library-add-form',
      'js-media-library-add-form',
    ];

    /** @var \Drupal\media\Entity\Media[] $added_media */
    $added_media = $form_state->get('media');

    $form['active_directory'] = [
      '#type' => 'hidden',
      '#value' => $this->getDirectory($form_state),
    ];

    $target_bundles = $this->getTargetBundles($form_state);

    $form['target_bundles'] = [
      '#tree' => TRUE,
    ];

    foreach ($target_bundles as $bundle) {
      $form['target_bundles'][$bundle] = [
        '#type' => 'hidden',
        '#value' => $bundle,
      ];
    }


    if (empty($added_media)) {
      $form['#attributes']['class'][] = 'media-library-add-form--without-input';
      $form = $this->buildInputElement($form, $form_state);
    }
    else {
      $form['#attributes']['class'][] = 'media-library-add-form--with-input';

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

      $form['media']['description'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->formatPlural(count($added_media), 'The media item has been created but has not yet been saved. Fill in any required fields and save to add it to the media library.', 'The media items have been created but have not yet been saved. Fill in any required fields and save to add them to the media library.'),
        '#attributes' => [
          'class' => [
            'media-library-add-form__description',
          ],
        ],
      ];

      foreach ($added_media as $delta => $media) {
        // $media->set('directory', $this->directoryId);
        $form['media'][$delta] = $this->buildEntityFormElement($media, $form, $form_state, $delta);
      }

      $form['actions'] = $this->buildActions($form, $form_state);
    }

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

  abstract protected function buildInputElement(array $form, FormStateInterface $form_state);

  public function validateForm(array &$form, FormStateInterface $form_state) {
    /*if (!$form_state->isValueEmpty('upload')) {
      $entities = $this->prepareEntities($form, $form_state);
      $form_state->setValue('media', $entities);
      $form_state->setStorage(['media' => $entities]);
      $form_state->setRebuild();
    }*/
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
    $added_media = $form_state->get('media');

    $tmp_tid_mids = [];
    foreach ($added_media as $delta => $media) {
      EntityFormDisplay::collectRenderDisplay($media, 'media_library')
        ->extractFormValues($media, $form['media'][$delta]['fields'], $form_state);
      //$this->prepareMediaEntityForSave($media);
      $media->save();
      $tmp_tid_mids[(isset($media->get('directory')->target_id) ? $media->get('directory')->target_id : MEDIA_DIRECTORY_ROOT)][] = $media->id();
    }

    // Support multi value fields.
    $tid_holding_most_mids  = -1;
    foreach ($tmp_tid_mids as $tid => $mids) {
      if (!isset($tmp_tid_mids[$tid_holding_most_mids]) ||
        (count($tmp_tid_mids[$tid_holding_most_mids]) < count($tmp_tid_mids[$tid]))) {
        $tid_holding_most_mids = $tid;
      }
    }

    $form_state->setValue('newly_added_media_ids', $tmp_tid_mids[$tid_holding_most_mids]);
    $form_state->setValue('most_choosen_directory_tid', $tid_holding_most_mids);
  }

  /**
   * Prepare uploaded media entities.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function prepareEntities(array $form, FormStateInterface $form_state, $field_name) {
    $entities = [];
    $media_type = $this->getMediaType($form_state);

    foreach ($form_state->getValue($field_name) as $fid) {
      $file = File::load($fid);
      $entities[] = $this->entityTypeManager->getStorage('media')->create([
        'bundle' => $media_type->id(),
        $media_type->getSource()->getConfiguration()['source_field'] => $file,
        'uid' => $this->currentUser->id(),
        'status' => TRUE,
        'type' => $media_type->getSource()->getPluginId(),
        'directory' => $this->getDirectory($form_state),
      ]);
    }

    return $entities;
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
   */
  protected function buildEntityFormElement(MediaInterface $media, array $form, FormStateInterface $form_state, $delta) {
    // We need to make sure each button has a unique name attribute. The default
    // name for button elements is 'op'. If the name is not unique, the
    // triggering element is not set correctly and the wrong media item is
    // removed.
    // @see ::removeButtonSubmit()
    $parents = (isset($form['#parents']) ? $form['#parents'] : '');
    $id_suffix = $parents ? '-' . implode('-', $parents) : '';

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
      'remove_button' => [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#name' => 'media-' . $delta . '-remove-button' . $id_suffix,
        '#weight' => 30,
        '#attributes' => [
          'class' => ['media-library-add-form__remove-button'],
          'aria-label' => $this->t('Remove @label', ['@label' => $media->getName()]),
        ],
        '#ajax' => [
          'callback' => '::updateFormCallback',
          'wrapper' => 'media-library-add-form-wrapper',
          'message' => $this->t('Removing @label.', ['@label' => $media->getName()]),
        ],
        '#submit' => ['::removeButtonSubmit'],
        // Ensure errors in other media items do not prevent removal.
        '#limit_validation_errors' => [],
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

    // We hide the preview of the uploaded file in the image widget with CSS.
    // @todo Improve hiding file widget elements in
    //   https://www.drupal.org/project/drupal/issues/2987921
    $source_field_name = $this->getSourceFieldName($this->getMediaType($form_state));
    if (isset($element['fields'][$source_field_name])) {
      $element['fields'][$source_field_name]['#attributes']['class'][] = 'media-library-add-form__source-field';
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
      'save_select' => [
        '#type' => 'submit',
        '#button_type' => 'primary',
        '#value' => $this->t('Save and select'),
        '#ajax' => [
          'callback' => '::updateLibrary',
          'wrapper' => 'media-library-add-form-wrapper',
        ],
      ],
    ];
  }

  /**
   * AJAX callback to update the entire form based on source field input.
   *
   * @param array $form
   *   The complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|array
   *   The form render array or an AJAX response object.
   */
  public function updateFormCallback(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $wrapper_id = $triggering_element['#ajax']['wrapper'];
    $added_media = $form_state->get('media');
    $media_type = $this->getMediaType($form_state);

    $response = new AjaxResponse();

    // When the source field input contains errors, replace the existing form to
    // let the user change the source field input. If the user input is valid,
    // the entire modal is replaced with the second step of the form to show the
    // form fields for each media item.
    if ($form_state::hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#media-library-add-form-wrapper', $form));
      return $response;
    }

    // Check if the remove button is clicked.
    if (end($triggering_element['#parents']) === 'remove_button') {
      // When the list of added media is empty, return to the media library and
      // shift focus back to the first tabbable element (which should be the
      // source field).
      if (empty($added_media)) {
        // TODO throws an ajax exception when trying to remove last item.
        $build = [
          '#theme' => 'media_directories_add',
          '#selected_type' => $media_type->id(),
          '#active_directory' => $this->getDirectory($form_state),
          '#target_bundles' => $this->getTargetBundles($form_state),
        ];
        $response->addCommand(new ReplaceCommand('#media-library-add-form-wrapper', $build));
        //$response->addCommand(new InvokeCommand('#media-library-add-form-wrapper :tabbable', 'focus'));
      }
      // When there are still more items, update the form and shift the focus to
      // the next media item. If the last list item is removed, shift focus to
      // the previous item.
      else {
        $response->addCommand(new ReplaceCommand("#$wrapper_id", $form));

      }
    }
    // Update the form and shift focus to the added media items.
    else {
      $response->addCommand(new ReplaceCommand("#$wrapper_id", $form));
    }

    return $response;
  }

  /**
   * Submit handler for the remove button.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function removeButtonSubmit(array $form, FormStateInterface $form_state) {
    // Retrieve the delta of the media item from the parents of the remove
    // button.
    $triggering_element = $form_state->getTriggeringElement();
    $delta = array_slice($triggering_element['#array_parents'], -2, 1)[0];

    $added_media = $form_state->get('media');
    $removed_media = $added_media[$delta];

    // Update the list of added media items in the form state.
    unset($added_media[$delta]);

    // Update the media items in the form state.
    $form_state->set('media', $added_media)->setRebuild();

    // Show a message to the user to confirm the media is removed.
    $this->messenger()->addStatus($this->t('The media item %label has been removed.', ['%label' => $removed_media->label()]));
  }

  /**
   * AJAX callback to send the new media item(s) to the media library.
   *
   * @param array $form
   *   The complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array|\Drupal\Core\Ajax\AjaxResponse
   *   The form array if there are validation errors, or an AJAX response to add
   *   the created items to the current selection.
   */
  public function updateLibrary(array &$form, FormStateInterface $form_state) {
    if ($form_state::hasAnyErrors()) {
      return $form;
    }

/*    $media_ids = array_map(function (MediaInterface $media) {
      return $media->id();
    }, $this->getAddedMediaItems($form_state));*/

    $form_state->setStorage([]);
    $form_state->setRebuild();

    $response = new AjaxResponse();
    //$response->addCommand(new UpdateSelectionCommand($media_ids));
    $response->addCommand(new CloseModalDialogCommand());
    $response->addCommand(new RefreshDirectoryTree($form_state->getValue('most_choosen_directory_tid'), $form_state->getValue('newly_added_media_ids')));

    return $response;
  }

  /**
   * Creates media items from source field input values.
   *
   * @param mixed[] $source_field_values
   *   The values for source fields of the media items.
   * @param array $form
   *   The complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  protected function processInputValues(array $source_field_values, array $form, FormStateInterface $form_state) {
    $media_type = $this->getMediaType($form_state);
    $media_storage = $this->entityTypeManager->getStorage('media');
    $source_field_name = $this->getSourceFieldName($media_type);
    $media = array_map(function ($source_field_value) use ($media_type, $media_storage, $source_field_name, $form_state) {
      return $this->createMediaFromValue($media_type, $media_storage, $source_field_name, $source_field_value, $form_state);
    }, $source_field_values);
    // Re-key the media items before setting them in the form state.
    $form_state->set('media', array_values($media));
    // Save the selected items in the form state so they are remembered when an
    // item is removed.
    //$form_state->set('current_selection', array_filter(explode(',', $form_state->getValue('current_selection'))));
    $form_state->setRebuild();
  }

  /**
   * Creates a new, unsaved media item from a source field value.
   *
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The media type of the media item.
   * @param \Drupal\Core\Entity\EntityStorageInterface $media_storage
   *   The media storage.
   * @param string $source_field_name
   *   The name of the media type's source field.
   * @param mixed $source_field_value
   *   The value for the source field of the media item.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return \Drupal\media\MediaInterface
   *   An unsaved media entity.
   */
  protected function createMediaFromValue(MediaTypeInterface $media_type, EntityStorageInterface $media_storage, $source_field_name, $source_field_value, FormStateInterface $form_state) {
    $media = $media_storage->create([
      'bundle' => $media_type->id(),
      $source_field_name => $source_field_value,
      'directory' => $this->getDirectory($form_state),
    ]);
    $media->setName($media->getName());
    return $media;
  }

}
