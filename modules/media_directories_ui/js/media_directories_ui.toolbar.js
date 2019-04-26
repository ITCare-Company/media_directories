(function ($, Drupal) {

  Drupal.MediaBrowser.toolbar = {
    buttons: {
      media_add: $('#browser-add-media'),
      media_edit: $('#browser-edit-media'),
      media_delete: $('#browser-delete-media'),
      submit: $('#edit-submit')
    },
    init: function () {
      this.buttons.media_add.on('click', function (e) {
        e.preventDefault();
        let ajaxSettings = {
          url: Drupal.MediaBrowser.getUrl('media.add'),
          submit: {
            active_directory: Drupal.MediaBrowser.active_directory,
            target_bundles: Drupal.MediaBrowser.targetBundles,
            media_library_opener_id: 'test',
            media_library_allowed_types: Drupal.MediaBrowser.targetBundles,
            media_library_selected_type: 'image',
            // TODO
            media_library_remaining: '10'
          }
        };

        Drupal.ajax(ajaxSettings).execute();
      });

      this.buttons.media_edit.on('click', function (e) {
        e.preventDefault();

        if ($(this).hasClass('is-disabled')) {
          return;
        }

        let $selected_item = $('.media-item.selected');
        let ajaxSettings = {
          url: $selected_item.data('edit-url')
        };

        Drupal.ajax(ajaxSettings).execute();
      });

      this.buttons.media_delete.on('click', function (e) {
        e.preventDefault();

        if ($(this).hasClass('is-disabled')) {
          return;
        }
        let mids = [];

        $('.media-item.selected').map(function () {
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

      this.selectionChanged();

    },
    selectionChanged: function () {
      const $selected = $('.media-item.selected');

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
        this.buttons.media_edit.addClass('is-disabled');
        this.buttons.media_delete.removeClass('is-disabled');
        this.buttons.submit.removeAttr('disabled');
      }
    }
  };

})(jQuery, Drupal);
