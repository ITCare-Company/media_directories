(function ($, Drupal) {

  /**
   * Media Browser toolbar functionality.
   *
   * @type {{init: Drupal.MediaBrowser.toolbar.init, buttons: {submit: (*|jQuery|HTMLElement), media_edit: (*|jQuery|HTMLElement), media_delete: (*|jQuery|HTMLElement), media_add: (*|jQuery|HTMLElement)}, selectionChanged: Drupal.MediaBrowser.toolbar.selectionChanged}}
   */
  Drupal.MediaBrowser.toolbar = {
    buttons: {
      media_add: $('#browser-add-media'),
      media_edit: $('#browser-edit-media'),
      media_delete: $('#browser-delete-media'),
      submit: $('#edit-submit')
    },
    init: function () {
      // Add new media button.
      this.buttons.media_add.on('click', function (e) {
        e.preventDefault();
        let ajaxSettings = {
          url: Drupal.MediaBrowser.getUrl('media.add'),
          submit: {
            active_directory: Drupal.MediaBrowser.active_directory,
            target_bundles: Drupal.MediaBrowser.targetBundles,
          }
        };

        Drupal.ajax(ajaxSettings).execute();
      });

      // Edit media button.
      this.buttons.media_edit.on('click', function (e) {
        e.preventDefault();
        let mids = [];

        if ($(this).hasClass('is-disabled')) {
          return;
        }

        $.each(Drupal.MediaBrowser.getSelectedElements(), function () {
          mids.push($(this).data('mid'));
        });

        let ajaxSettings = {
          url: Drupal.MediaBrowser.getUrl('media.edit'),
          submit: {
            active_directory: Drupal.MediaBrowser.active_directory,
            media_items: mids
          }
        };

        Drupal.ajax(ajaxSettings).execute();
      });

      // Delete media button.
      this.buttons.media_delete.on('click', function (e) {
        e.preventDefault();

        if ($(this).hasClass('is-disabled')) {
          return;
        }
        let mids = [];

        $.each(Drupal.MediaBrowser.getSelectedElements(), function () {
          mids.push($(this).data('mid'));
        });

        let ajaxSettings = {
          url: Drupal.MediaBrowser.getUrl('media.delete'),
          submit: {
            media_items: mids,
          }
        };

        Drupal.ajax(ajaxSettings).execute();
      });

      // Set initial button states.
      this.selectionChanged();
    },
    /**
     * Set correct states for toolbar buttons.
     */
    selectionChanged: function () {
      const $selected = Drupal.MediaBrowser.getSelectedElements();

      if ($selected.length === 1) {
        this.buttons.media_edit.removeClass('is-disabled');
        this.buttons.media_delete.removeClass('is-disabled');
        this.buttons.submit.removeAttr('disabled');
      }
      else if ($selected.length === 0) {
        this.buttons.media_edit.addClass('is-disabled');
        this.buttons.media_delete.addClass('is-disabled');
        this.buttons.submit.attr('disabled', 'disabled');
      }
      else {
        this.buttons.media_edit.removeClass('is-disabled');
        this.buttons.media_delete.removeClass('is-disabled');
        this.buttons.submit.removeAttr('disabled');
      }
    }
  };

})(jQuery, Drupal);
