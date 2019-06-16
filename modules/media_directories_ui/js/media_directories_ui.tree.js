(function ($, Drupal, drupalSettings) {

  Drupal.MediaBrowser.tree = function () {
    $(Drupal.MediaBrowser.treeSelector).jstree({
      plugins : [ 'dnd', 'wholerow', 'contextmenu', 'sort' ],
      multiple: false,
      core : {
        check_callback: Drupal.MediaBrowser.treeCheckCallback,
        data : {
          url : Drupal.MediaBrowser.getUrl('directory.tree'),
          data : function (node) {
            return { 'id' : node.id };
          }
        },
        themes: {
          ellipsis: true
        }
      },
      dnd: {
        copy: false,
        is_draggable: function (nodes) {
          // Root node is not draggable.
          let is_root = nodes[0].id === 'dir-root';
          return !is_root;
        }
      },
      contextmenu : {
        items: Drupal.MediaBrowser.treeContextMenu
      }
    });

    $(Drupal.MediaBrowser.treeSelector).once().each(function () {
      $(this).on('changed.jstree', function (e, data) {
        if (data.action === 'select_node') {
          let directory_id = data.node.a_attr["data-tid"];
          Drupal.MediaBrowser.loadDirectoryContent(directory_id);
          Drupal.MediaBrowser.active_directory = directory_id;
          // Clear selection from global storage.
          Drupal.MediaBrowser.selectedMedia = [];
        }
      });

      $(this).on('loaded.jstree', function () {
        Drupal.MediaBrowser.loadDirectoryContent(-1);
        // Clear selection from global storage.
        Drupal.MediaBrowser.selectedMedia = [];
      });

      $(this).on('rename_node.jstree', function (event, data) {
        let directory_id = $('#' + data.node.a_attr['id']).data('tid');
        Drupal.MediaBrowser.renameDirectory(directory_id, data.text);
      });
    });

  };

  /**
   * Load directory content.
   */
  Drupal.MediaBrowser.loadDirectoryContent = function (directory_id) {
    let ajaxSettings = {
      url: Drupal.MediaBrowser.getUrl('directory.content'),
      submit: {
        directory_id: directory_id,
        target_bundles: Drupal.MediaBrowser.target_bundles
      }
    };

    Drupal.ajax(ajaxSettings).execute().done(function () {
      Drupal.MediaBrowser.media.init($('.browser--listing'));
    });
  };

  /**
   * Move media item(s) into directory.
   */
  Drupal.MediaBrowser.moveMediaToDirectory = function (media_items, directory_id) {
    if (media_items && directory_id) {
      let ajaxSettings = {
        url: Drupal.MediaBrowser.getUrl('media.move'),
        submit: {
          directory_id: directory_id,
          media_items: media_items,
          target_bundles: Drupal.MediaBrowser.target_bundles,
        }
      };

      Drupal.ajax(ajaxSettings).execute();
    }
    else {
      console.log('Parameters missing!');
    }
  };

  /**
   * Move directory into directory.
   *
   * @param move_directory_id
   * @param directory_id
   */
  Drupal.MediaBrowser.moveDirectoryToDirectory = function (move_directory_id, directory_id) {
    if (move_directory_id && directory_id) {
      let ajaxSettings = {
        url: Drupal.MediaBrowser.getUrl('directory.move'),
        submit: {
          directory_id: directory_id,
          move_directory_id: move_directory_id,
          target_bundles: Drupal.MediaBrowser.target_bundles,
        }
      };

      Drupal.ajax(ajaxSettings).execute();
    }
    else {
      console.log('Parameters missing!');
    }
  };

  /**
   * Rename directory.
   *
   * @param directory_id
   * @param new_name
   */
  Drupal.MediaBrowser.renameDirectory = function (directory_id, new_name) {
    let ajaxSettings = {
      url: this.MediaBrowser.getUrl('directory.rename'),
      submit: {
        directory_id: directory_id,
        directory_new_name: new_name,
      }
    };

    Drupal.ajax(ajaxSettings).execute();
  };

  /**
   * Delete directory.
   *
   * @param directory_id
   */
  Drupal.MediaBrowser.deleteDirectory = function (directory_id) {
    if (directory_id === -1 || directory_id === '') {
      console.log('Parameter missing!');
      return;
    }

    let ajaxSettings = {
      url: Drupal.MediaBrowser.getUrl('directory.delete'),
      submit: {
        directory_id: directory_id,
        target_bundles: Drupal.MediaBrowser.target_bundles
      }
    };

    Drupal.ajax(ajaxSettings).execute();
  };

  /**
   * Callback for checking if operation is allowed.
   *
   * @param operation
   * @param node
   * @param node_parent
   * @param node_position
   * @param more
   * @returns {boolean}
   */
  Drupal.MediaBrowser.treeCheckCallback = function (operation, node, node_parent, node_position, more) {
    // Media item is foreign and we don't allow any modifications.
    if (more && more.is_foreign) {
      return false;
    }

    // While dragging, don't allow dropping between nodes.
    if (operation === 'move_node' && node_position === 0 && more.pos === 'i') {
      return true;
    }
    // Create/rename operations are allowed.
    else if (operation === 'create_node' || operation === 'rename_node' || operation === 'edit') {
      return true;
    }

    return false;
  };

  /**
   * Callback for context menu items.
   * @param node
   * @returns {{rename: {_disabled: boolean, action: rename.action, label: *},
   *   new_directory: {action: new_directory.action, label: *}, delete:
   *   {_disabled: boolean, action: delete.action, label: *}}}
   */
  Drupal.MediaBrowser.treeContextMenu = function (node) {
    // Root node cannot be deleted or renamed.
    let is_root = node.id === 'dir-root';
    // Elements with children cannot be deleted!
    let has_children = node.children.length > 0;

    return {
      new_directory: {
        label: Drupal.t('New folder'),
        action: function (data) {
          let inst = $.jstree.reference(data.reference),
            obj = inst.get_node(data.reference);
          $.post({
            url: Drupal.MediaBrowser.getUrl('directory.add'),
            data: {
              action: 'create_directory',
              parent_id: obj.a_attr['data-tid'],
              name: Drupal.t('New folder')
            },
            success: function (data) {
              inst.create_node(obj, data, "last", function (new_node) {
                try {
                  inst.edit(new_node);
                }
                catch (ex) {
                  setTimeout(function () { inst.edit(new_node); },0);
                }
              });
            }
          });
        }
      },
      rename: {
        label: Drupal.t('Rename'),
        _disabled: is_root,
        action: function (data) {
          let inst = $.jstree.reference(data.reference),
            obj = inst.get_node(data.reference);
          inst.edit(obj);
        }
      },
      delete: {
        label: Drupal.t('Delete'),
        _disabled: is_root || has_children,
        action: function (node) {
          Drupal.MediaBrowser.deleteDirectory($(node.reference).data('tid'));
        },
      }
    };
  }

})(jQuery, Drupal, drupalSettings);
