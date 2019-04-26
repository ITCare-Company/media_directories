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
   * RefreshDirectoryTree constructor.
   *
   * @param int $selected_directory
   */
  public function __construct($selected_directory = -1) {
    $this->selected_directory = $selected_directory;
  }

  /**
   * Implements \Drupal\Core\Ajax\CommandInterface:render().
   */
  public function render() {
    return [
      'command' => 'refreshDirectoryTree',
      'data' => [
        'selected_directory' => $this->selected_directory,
      ]
    ];
  }

}
