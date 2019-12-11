<?php

namespace Drupal\media_directories_ui\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Class RefreshDirectoryTree.
 */
class RefreshDirectoryTree implements CommandInterface {

  /**
   * @var
   */
  protected $selected_directory;

  /**
   * @var
   */
  protected $newly_selected_entity_ids;

  /**
   * RefreshDirectoryTree constructor.
   *
   * @param int $selected_directory
   */
  public function __construct($selected_directory = MEDIA_DIRECTORY_ROOT, $newly_selected_entity_ids = []) {
    $this->selected_directory = $selected_directory;
    $this->newly_selected_entity_ids = $newly_selected_entity_ids;
  }

  /**
   * Implements \Drupal\Core\Ajax\CommandInterface:render().
   */
  public function render() {
    return [
      'command' => 'refreshDirectoryTree',
      'data' => [
        'selected_directory' => (isset($this->selected_directory) ? $this->selected_directory : MEDIA_DIRECTORY_ROOT),
        'newly_selected_entity_ids' => $this->newly_selected_entity_ids,
      ]
    ];
  }

}
