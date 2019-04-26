<?php
namespace Drupal\media_directories_ui\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media\MediaForm;
use Drupal\media_directories_ui\Ajax\LoadDirectoryContent;

/**
 * Class BrowserMediaForm
 *
 * @package Drupal\media_directories_ui\Form
 */
class BrowserMediaForm extends MediaForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $storage = $form_state->getStorage();

    if (isset($storage['media_directories']) && $storage['media_directories']['use_ajax']) {
      // Display status messages.
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];

      // Add special wrapper with ID for ajax to replace.
      $form['#theme_wrappers'] = [
        'form',
        'container' => [
          '#attributes' => ['id' => 'media-edit-form']
        ]
      ];

      // Hide revision info, we probably don't need it.
      $form['revision_information']['#access'] = FALSE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    // Hide delete link.
    $actions['delete']['#access'] = FALSE;

    // Add AJAX callback to submit button.
    $actions['submit']['#ajax'] = [
      'callback' => [$this, 'ajaxSubmit'],
      'event' => 'click',
    ];

    return  $actions;
  }

  /**
   * Ajax submit callback.
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public function ajaxSubmit($form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $storage = $form_state->getStorage();

    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#media-edit-form', $form));
      return $response;
    }

    $response->addCommand(new CloseModalDialogCommand());

    if (isset($storage['media_directories'])) {
      $response->addCommand(new LoadDirectoryContent());
    }

    return $response;
  }

}
