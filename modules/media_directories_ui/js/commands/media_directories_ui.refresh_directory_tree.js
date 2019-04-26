(function ($, Drupal) {
  Drupal.AjaxCommands.prototype.refreshDirectoryTree = function (ajax, response, status) {
    const $jsTree = $(Drupal.MediaBrowser.treeSelector);
    let selected_directory = parseInt(response.data.selected_directory);

    $jsTree.one('refresh.jstree', function() {
      let node_id = selected_directory === -1 ? 'dir-root' : 'dir-' + selected_directory;
      $(this).jstree(true).deselect_all();
      $(this).jstree(true).select_node(node_id);
      $(this).jstree(true).open_node(node_id);
    });

    $jsTree.jstree(true).refresh(false, true);

  }
})(jQuery, Drupal);
