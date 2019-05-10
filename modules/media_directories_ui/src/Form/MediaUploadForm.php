<?php
namespace Drupal\media_directories_ui\Form;

use Drupal\Component\Utility\Bytes;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\dropzonejs\DropzoneJsUploadSaveInterface;
use Drupal\dropzonejs\Events\DropzoneMediaEntityCreateEvent;
use Drupal\dropzonejs\Events\Events;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\media\Entity\Media;
use Drupal\media_directories_ui\Ajax\LoadDirectoryContent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class MediaUploadForm
 * TODO: remove this class.
 *
 * @deprecated will be removed
 *
 * @package Drupal\media_directories_ui\Form
 */
class MediaUploadForm extends FormBase {

  /**
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * DropzoneJS module upload save service.
   *
   * @var \Drupal\dropzonejs\DropzoneJsUploadSaveInterface
   */
  protected $dropzoneJsUploadSave;

  /**
   * Current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * MediaUploadForm constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\dropzonejs\DropzoneJsUploadSaveInterface $dropzonejs_upload_save
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   */
  public function __construct(EventDispatcherInterface $event_dispatcher, EntityTypeManagerInterface $entity_type_manager, DropzoneJsUploadSaveInterface $dropzonejs_upload_save, AccountProxyInterface $current_user) {
    $this->eventDispatcher = $event_dispatcher;
    $this->entityTypeManager = $entity_type_manager;
    $this->dropzoneJsUploadSave = $dropzonejs_upload_save;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('event_dispatcher'),
      $container->get('entity_type.manager'),
      $container->get('dropzonejs.upload_save'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_upload_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $build_info = $form_state->getBuildInfo();
    $target_types = isset($build_info['args'][1]) ? $build_info['args'][1] : NULL;

    // Add special wrapper with ID for ajax to replace.
    $form['#theme_wrappers'] = [
      'form',
      'container' => [
        '#attributes' => ['id' => 'media-add-form']
      ]
    ];

    // Display status messages.
    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];

    $form['active_directory'] = [
      '#type' => 'hidden',
      '#default_value' => isset($build_info['args'][0]) ? (int)$build_info['args'][0] : -1,
    ];

    $form['media_types'] = [
      '#type' => 'vertical_tabs',
    ];

    if ($target_types !== NULL) {
      $form['target_bundles']['#tree'] = TRUE;

      foreach ($target_types as $target_type) {
        $form['target_bundles'][$target_type] = [
          '#type' => 'hidden',
          '#value' => $target_type,
        ];
      }
    }

    /** @var \Drupal\media\Entity\MediaType[] $types */
    $types = $this->entityTypeManager->getStorage('media_type')->loadMultiple();

    foreach ($types as $type) {
      $form['media_' . $type->id()] = [
        '#type' => 'details',
        '#title' => $type->label(),
        '#group' => 'media_types',
        '#tree' => TRUE,
      ];

      $max_filesize = \Drupal\Component\Utility\Environment::getUploadMaxSize();

      /*$form['media_' . $type->id()]['upload']['files'] = [
        '#type' => 'dropzonejs',
        '#title' => $this->t('Select files'),
        '#dropzone_description' => $this->t('Drag and drop files'),
        '#max_filesize_description' => $this->t('Maximum upload size: @size', ['@size' => format_size($max_filesize)]),
        '#extensions' => $this->getValidExtensions([$type->id()]),
        '#extensions_description' => $this->t('Allowed file extensions: @extensions', ['@extensions' => $this->getValidExtensions([$type->id()])]),
        '#max_files' => 0,
        '#clientside_resize' => TRUE,
        '#thumbnail_method' => 'crop',
        '#theme' => 'dropzonejs__media_upload',
      ];*/

      $source_field = $type->getSource()->getConfiguration()['source_field'];
      $field_config = $this->entityTypeManager->getStorage('field_config')->load('media.' . $type->id() .'.' . $source_field);

      if (in_array($field_config->getType(), ['file', 'image'])) {
        $form['media_' . $type->id()]['upload']['files'] = [
          '#type' => 'managed_file',
          '#title' => $field_config->label(),
          '#description' => $this->t('Allowed file extensions: @extensions', ['@extensions' => $field_config->getSetting('file_extensions')]),
          '#upload_validators' => [
            'file_validate_extensions' => [$field_config->getSetting('file_extensions')],
          ],
          '#multiple' => TRUE,
          '#upload_location' => 'public://media-directories/',
        ];
        $form['media_' . $type->id()]['upload']['media_type'] = [
          '#type' => 'value',
          '#value' => $type->id(),
        ];
      }
      else {
        $form['media_' . $type->id()][$field_config->bundle()] = [
          '#type' => 'textfield',
          '#title' => $field_config->label(),
          '#description' => $field_config->getDescription(),
        ];
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    /*$form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add media'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => [$this, 'submitModalAjax'],
        'event' => 'click',
      ]
    ];*/

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    /** @var \Drupal\Core\GeneratedUrl $url */
    //$url = Url::fromRoute('dropzonejs.upload')->toString(TRUE);
    // Merge csrf token placeholders into form array,
    // tokens are not replaced correctly when form is called by ajax.
    // @see https://www.drupal.org/project/drupal/issues/2630920
    //$form['#attached'] = array_merge($form['#attached'], $url->getAttachments());

    if (!$form_state->isValueEmpty('media_entities')) {
      $media_entities = $form_state->getValue('media_entities');

      foreach ($media_entities as $media_entity) {
        $form_display = EntityFormDisplay::collectRenderDisplay($media_entity, 'media_library');
      }

    }

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $media_type = $form_state->getValue('media_type');

    if ($media_type === 'video') {

      if ($form_state->isValueEmpty(['video', 'urls'])) {
        $form_state->setError($form['video']['urls'], $this->t('Cannot add media: add at least one URL!'));
      }
      else {
        $urls = explode("\n", $form_state->getValue(['video', 'urls']));
        $cleaned_urls = [];

        foreach ($urls as $url) {
          $url = trim($url);
          // Ignore empty lines and don't mark them as errors.
          if (empty($url)) {
            continue;
          }

          // Check if url is valid.
          if (UrlHelper::isValid($url, TRUE)) {
            $cleaned_urls[] = $url;
          }
          else {
            $form_state->setError($form['video']['urls'], $this->t('One or more URLs are not incorrect format!'));
            break;
          }
        }

        $form_state->setValue(['video', 'cleaned_urls'], $cleaned_urls);
      }
    }
    elseif ($media_type === 'upload') {
      if ($form_state->isValueEmpty(['upload', 'files', 'uploaded_files'])) {
        $form_state->setError($form['upload']['files'], $this->t('Add at least one file!'));
      }

    }

    if (!$form_state->isValueEmpty(['media_file', 'upload', 'files'])) {
      $entities = $this->prepareEntities($form, $form_state);
      $form_state->setValue('media_entities', $entities);
    }
  }

  /**
   * Ajax submit handler.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function submitModalAjax(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#media-add-form', $form));
      return $response;
    }

    $media_type = $form_state->getValue('media_type');

    if ($media_type === 'video') {
      $this->saveEmbeddedVideo($form, $form_state);
    }
    else {
      $this->saveUploadedMedia($form, $form_state);
    }

    //$this->selectEntities($media_entities, $form_state);
    //$this->clearFormValues($element, $form_state);

    $response->addCommand(new CloseModalDialogCommand());
    $response->addCommand(new LoadDirectoryContent());

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareEntities(array $form, FormStateInterface $form_state) {
    $entities = [];

    foreach ($this->getFiles($form, $form_state) as $file) {



      $media_type = $this->getType($form_state->getValue('media_type'));
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
   * Returns media type for specific file by mime type.
   *
   * @param FileInterface $file
   *
   * @return \Drupal\media\MediaTypeInterface
   *   Media type.
   */
  protected function getType(FileInterface $file) {
    /** @var \Drupal\media\Entity\MediaType[] $types */
    $types = $this->entityTypeManager->getStorage('media_type')->loadMultiple();
    // TODO better detection needed!
    switch ($file->getMimeType()) {
      case 'image/jpeg':
      case 'image/gif':
      case 'image/png':
        $file_type = 'image';
        break;
      default:
        $file_type = 'document';
        break;
    }

    return $types[$file_type];
  }

  /**
   * Gets uploaded files.
   *
   * We implement this to allow child classes to operate on different entity
   * type while still having access to the files in the validate callback here.
   *
   * @param array $form
   *   Form structure.
   * @param FormStateInterface $form_state
   *   Form state object.
   *
   * @return \Drupal\file\FileInterface[]
   *   Array of uploaded files.
   */
  protected function getFiles(array $form, FormStateInterface $form_state) {
    //$config = $this->getConfiguration();
    $additional_validators = ['file_validate_size' => [file_upload_max_size(), 0]];

    $files = $form_state->get(['media_file','upload', 'files']);

    if (!$files) {
      $files = [];
    }

    // We do some casting because $form_state->getValue() might return NULL.
    foreach ((array) $form_state->getValue(['upload', 'files', 'uploaded_files'], []) as $file) {
      if (file_exists($file['path'])) {
        $entity = $this->dropzoneJsUploadSave->createFile(
          $file['path'],
          $this->getUploadLocation(),
          $form['upload']['files']['#extensions'],
          $this->currentUser,
          $additional_validators
        );
        $files[] = $entity;
      }
    }

   /* if ($form['widget']['upload']['#max_files']) {
      $files = array_slice($files, -$form['widget']['upload']['#max_files']);
    }*/

    $form_state->set(['upload', 'files'], $files);

    return $files;
  }

  /**
   * Gets upload location.
   *
   * @return string
   *   Destination folder URI.
   */
  protected function getUploadLocation() {
    return 'public://dropzone-uploads';
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
    // TODO: Implement submitForm() method.
  }

  /**
   * Save uploaded file as media entity.
   * Tries to autodetect by mime type the correct media type.
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  protected function saveUploadedMedia(&$form, FormStateInterface $form_state) {
    $directory = $form_state->getValue('active_directory') > 0 ? (int)$form_state->getValue('active_directory') : NULL;
    /** @var \Drupal\media\MediaInterface[] $media_entities */
    $media_entities = $this->prepareEntities($form, $form_state);
    //$source_field = $this->getType()->getSource()->getConfiguration()['source_field'];

    foreach ($media_entities as &$media_entity) {
      $source_field = $media_entity->getSource()->getConfiguration()['source_field'];
      $file = $media_entity->$source_field->entity;
      /** @var \Drupal\dropzonejs\Events\DropzoneMediaEntityCreateEvent $event */
      $event = $this->eventDispatcher->dispatch(Events::MEDIA_ENTITY_CREATE, new DropzoneMediaEntityCreateEvent($media_entity, $file, $form, $form_state, $form['upload']));
      $media_entity = $event->getMediaEntity();

      if ($directory) {
        $media_entity->get('directory')->setValue($directory);
      }

      $source_field = $media_entity->getSource()->getConfiguration()['source_field'];
      // If we don't save file at this point Media entity creates another file
      // entity with same uri for the thumbnail. That should probably be fixed
      // in Media entity, but this workaround should work for now.
      $media_entity->$source_field->entity->save();
      $media_entity->save();
    }
  }

  /**
   * Save embedded video media type.
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function saveEmbeddedVideo(&$form, FormStateInterface $form_state) {
    $directory = $form_state->getValue('active_directory') > 0 ? (int)$form_state->getValue('active_directory') : NULL;
    /** @var \Drupal\media\Entity\MediaType[] $types */
    $types = $this->entityTypeManager->getStorage('media_type')->loadMultiple();
    $type = $types['video_embedded'];
    $source_field = $type->getSource()->getConfiguration()['source_field'];

    $urls = $form_state->getValue(['video', 'cleaned_urls']);

    foreach ($urls as $url) {
      $video = Media::create([
        'bundle' => 'video_embedded',
        'uid' =>\Drupal::currentUser()->id(),
        'status' => 1,
        $source_field => [
          'value' => trim($url),
        ]
      ]);

      if ($directory) {
        $video->get('directory')->setValue($directory);
      }

      $video->save();
    }
  }

  /**
   * Collect all supported extensions.
   *
   * @param $target_types
   *
   * @return string
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getValidExtensions($target_types) {
    $valid_extensions = [];
    /** @var \Drupal\media\Entity\MediaType[] $types */
    $types = $this->entityTypeManager->getStorage('media_type')->loadMultiple();

    foreach ($types as $type) {

      if (!empty($target_types) && !isset($target_types[$type->id()])) {
        continue;
      }

      $source_field = $type->getSource()->getConfiguration()['source_field'];
      $field_config = $this->entityTypeManager->getStorage('field_config')->load('media.' . $type->id() .'.' . $source_field);
      $valid_extensions = array_merge($valid_extensions, explode(' ', $field_config->getSetting('file_extensions')));
    }

    $valid_extensions = array_unique($valid_extensions);

    return implode(' ', $valid_extensions);
  }

  public function changeMediaType(array &$form, FormStateInterface $form_state) {
    return $form;
  }
}
