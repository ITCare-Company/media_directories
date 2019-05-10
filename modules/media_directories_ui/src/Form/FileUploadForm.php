<?php

namespace Drupal\media_directories_ui\Form;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\media\MediaInterface;

class FileUploadForm extends AddMediaFormBase {

  protected function buildInputElement(array $form, FormStateInterface $form_state) {
    $max_filesize = \Drupal\Component\Utility\Environment::getUploadMaxSize();

    $process = (array) \Drupal::service('element_info')->getInfoProperty('managed_file', '#process', []);


    $source_field = $this->mediaType->getSource()->getConfiguration()['source_field'];
    $field_config = $this->entityTypeManager->getStorage('field_config')->load('media.' . $this->mediaType->id() .'.' . $source_field);

    $form['container']['upload'] = [
      '#type' => 'managed_file',
      '#title' => $field_config->label(),
      '#description' => $this->t('Allowed file extensions: @extensions', ['@extensions' => $field_config->getSetting('file_extensions')]),
      '#upload_validators' => [
        'file_validate_extensions' => [$field_config->getSetting('file_extensions')],
      ],
      '#multiple' => TRUE,
      '#upload_location' => 'public://media-directories/',
      '#process' => array_merge(['::validateUploadElement'], $process, ['::processUploadElement']),
    ];

    return $form;
  }

  /**
   * Validates the upload element.
   *
   * @param array $element
   *   The upload element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The processed upload element.
   */
  public function validateUploadElement(array $element, FormStateInterface $form_state) {
/*    if ($form_state::hasAnyErrors()) {
      // When an error occurs during uploading files, remove all files so the
      // user can re-upload the files.
      $element['#value'] = [];
    }
    $values = $form_state->getValue('upload', []);
    if (count($values['fids']) > $element['#cardinality'] && $element['#cardinality'] !== FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
      $form_state->setError($element, $this->t('A maximum of @count files can be uploaded.', [
        '@count' => $element['#cardinality'],
      ]));
      $form_state->setValue('upload', []);
      $element['#value'] = [];
    }*/
    return $element;
  }

  /**
   * Processes an upload (managed_file) element.
   *
   * @param array $element
   *   The upload element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The processed upload element.
   */
  public function processUploadElement(array $element, FormStateInterface $form_state) {
    $element['upload_button']['#submit'] = ['::uploadButtonSubmit'];
    // Limit the validation errors to make sure
    // FormValidator::handleErrorsWithLimitedValidation doesn't remove the
    // current selection from the form state.
    // @see Drupal\Core\Form\FormValidator::handleErrorsWithLimitedValidation()
    $element['upload_button']['#limit_validation_errors'] = [
      ['upload'],
      ['current_selection'],
    ];
    $element['upload_button']['#ajax'] = [
      'callback' => '::updateFormCallback',
      'wrapper' => 'media-library-wrapper',
      // Add a fixed URL to post the form since AJAX forms are automatically
      // posted to <current> instead of $form['#action'].
      // @todo Remove when https://www.drupal.org/project/drupal/issues/2504115
      //   is fixed.
/*      'url' => Url::fromRoute('media_library.ui'),
      'options' => [
        'query' => $this->getMediaLibraryState($form_state)->all() + [
            FormBuilderInterface::AJAX_FORM_REQUEST => TRUE,
          ],
      ],*/
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareMediaEntityForSave(MediaInterface $media) {
    /** @var \Drupal\file\FileInterface $file */
    $file = $media->get($this->getSourceFieldName($media->bundle->entity))->entity;
    $file->setPermanent();
    $file->save();
  }
}
