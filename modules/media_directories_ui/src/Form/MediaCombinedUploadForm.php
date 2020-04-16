<?php
namespace Drupal\media_directories_ui\Form;

use Drupal\Component\Utility\Environment;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Utility\Token;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MediaCombinedUploadForm extends FileUploadForm {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * AddMediaFormBase constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   * @param \Drupal\Core\Utility\Token $token
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   * @param \Drupal\Core\Render\ElementInfoManagerInterface $element_info
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user, Token $token, ThemeManagerInterface $theme_manager, ElementInfoManagerInterface $element_info, FileSystemInterface $file_system) {
    parent::__construct($entity_type_manager, $current_user, $token, $theme_manager, $element_info);
    $this->fileSystem = $file_system;
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
      $container->get('token'),
      $container->get('theme.manager'),
      $container->get('element_info'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_directories_combined_upload_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildInputElement(array $form, FormStateInterface $form_state) {
    $target_types = $this->getTargetBundles($form_state);
    $max_filesize = Environment::getUploadMaxSize();

    $form['container']['upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Select files'),
      '#description' => $this->t('Allowed file extensions: @extensions', ['@extensions' => $this->getValidExtensions($target_types)]),
      '#multiple' => TRUE,
      // Upload to temporary folder. Needs to be moved into correct folder after saving.
      '#upload_location' => 'temporary://',
      '#upload_validators' => [
        'file_validate_extensions' =>  [$this->getValidExtensions($target_types)],
        'file_validate_size' => [$max_filesize],
      ],
      '#process' => [['Drupal\file\Element\ManagedFile', 'processManagedFile'], '::processUploadElement'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareEntities(array $form, FormStateInterface $form_state, $field_name) {
    $entities = [];

    foreach ($this->getFiles($form, $form_state) as $file) {
      $media_type = $this->getType($file);
      $entities[] = $this->entityTypeManager->getStorage('media')->create([
        'bundle' => $media_type->id(),
        $media_type->getSource()->getConfiguration()['source_field'] => $file,
        'uid' => $this->currentUser->id(),
        'status' => TRUE,
        'type' => $media_type->getSource()->getPluginId(),
      ]);
    }

    return $entities;
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
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function processInputValues(array $source_field_values, array $form, FormStateInterface $form_state) {
    $media = [];

    foreach ($source_field_values as $source_field_value) {
      $media_type = $this->getMediaType($form_state, $source_field_value);
      $media_storage = $this->entityTypeManager->getStorage('media');
      $source_field_name = $this->getSourceFieldName($media_type);

      $field_config = $this->entityTypeManager->getStorage('field_config')->load('media.' . $media_type->id() .'.' . $source_field_name);
      $destination = $this->getUploadLocation($field_config->getSettings());
      if ($this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY)) {
        $source_field_value = file_move($source_field_value, $destination);
      }

      $media[] = $this->createMediaFromValue($media_type, $media_storage, $source_field_name, $source_field_value, $form_state);
    }

    // Re-key the media items before setting them in the form state.
    $form_state->set('media', array_values($media));
    $form_state->setRebuild();
  }

  /**
   * Returns media type for specific file by mime type.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @param \Drupal\file\FileInterface $file
   *
   * @return \Drupal\media\MediaTypeInterface
   *   Media type.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getMediaType(FormStateInterface $form_state, FileInterface $file = NULL) {
    if ($file === NULL) {
      return NULL;
    }

    /** @var \Drupal\media\Entity\MediaType[] $types */
    $types = $this->entityTypeManager->getStorage('media_type')->loadMultiple();

    $extension = pathinfo($file->getFileUri(), PATHINFO_EXTENSION);
    $target_types = $this->getTargetBundles($form_state);

    foreach ($types as $type) {

      if (!in_array($type->id(), $target_types, TRUE)) {
        continue;
      }

      $source_field = $type->getSource()->getConfiguration()['source_field'];
      $field_config = $this->entityTypeManager->getStorage('field_config')->load('media.' . $type->id() .'.' . $source_field);

      if (in_array($extension, explode(' ', $field_config->getSetting('file_extensions')))) {
        return $type;
      }
    }

    return NULL;
  }

  /**
   * Collect all supported extensions.
   *
   * @param $target_types
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getValidExtensions($target_types) {
    $valid_extensions = [];
    $ui_config = $this->config('media_directories_ui.settings');
    $combined_media_types = $ui_config->get('combined_upload_media_types');
    /** @var \Drupal\media\Entity\MediaType[] $types */
    $types = $this->entityTypeManager->getStorage('media_type')->loadMultiple();

    foreach ($types as $type) {

      if (!in_array($type->id(), $target_types, TRUE) || !in_array($type->id(), $combined_media_types, TRUE)) {
        continue;
      }

      $source_field = $type->getSource()->getConfiguration()['source_field'];
      $field_config = $this->entityTypeManager->getStorage('field_config')->load('media.' . $type->id() .'.' . $source_field);
      $valid_extensions = array_merge($valid_extensions, explode(' ', $field_config->getSetting('file_extensions')));
    }

    $valid_extensions = array_unique($valid_extensions);

    return implode(' ', $valid_extensions);
  }

  /**
   * {@inheritdoc}
   */
  public function updateFormCallback(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $wrapper_id = $triggering_element['#ajax']['wrapper'];
    $added_media = $form_state->get('media');

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
        $build = [
          '#theme' => 'media_directories_add',
          '#selected_type' => 'combined_upload',
          '#active_directory' => -1,
          '#target_bundles' => $this->getTargetBundles($form_state),
          '#media_library_form_rebuild' => TRUE,
        ];
        $form_state->setRebuild();
        $response->addCommand(new ReplaceCommand('#media-library-add-form-wrapper', $build));
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

}
