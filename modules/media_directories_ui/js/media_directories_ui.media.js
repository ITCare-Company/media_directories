(function ($, Drupal) {

  Drupal.MediaBrowser.media = {
    ctrlPressed: false,

    init: function () {
      const $browser_listing = $('.browser--listing');
      const cardinality = Drupal.MediaBrowser.cardinality;

      // Attach listener to the top document and current document to
      // register keypress inside iframe without focusing iframe first.
      $(top.document, document).on('keydown', function (e) {
        if (e.which === 17) {
          this.ctrlPressed = true;
        }
      });

      $(top.document, document).on('keyup', function () {
        this.ctrlPressed = false;
      });

      $browser_listing.find('.media-item').once().each(function () {
        $(this).on('click', function () {
          // TODO handle fixed cardinality > 1.
          if (cardinality === 1 || !Drupal.MediaBrowser.media.ctrlPressed) {
            $browser_listing.find('.media-item').each(function () {
              $(this).removeClass('selected');
              $('input[type="checkbox"]', this).prop('checked', false);
            });
            // Clear selection from global storage.
            Drupal.MediaBrowser.selectedMedia = [];
          }

          $(this).toggleClass('selected');
          let checkbox = $(this).find('input[type="checkbox"]');
          checkbox.prop("checked", !checkbox.prop("checked"));
          Drupal.MediaBrowser.selectedMedia.push($(this));
        });

        $(this).draggable({
          revert: true,
          helper: 'clone',
          start: function (e) {
            let nodes = [];
            let $drag_element = $(this);
            $.each(Drupal.MediaBrowser.selectedMedia, function () {
              // Do not include cloned element.
              if ($(this).is($drag_element)) {
                return true;
              }

              nodes.push({ id : this.dataset.mid, element: $(this) });
            });

            // If nothing is selected, then just use active item.
            if (nodes.length === 0) {
              nodes.push({ id : this.dataset.mid, element: $(this) });
            }

            let $html = $('<div id="jstree-dnd" class="jstree-default"></div>');

            $html.append('<i class="fas fa-arrows-alt"></i>');
            $html.append('<span class="jstree-items-count">' + Drupal.formatPlural(nodes.length, '1 item', '@count items') + '</span>');
            $html.append(nodes[0].element[0].outerHTML);

            return $.vakata.dnd.start(e, { 'jstree': true, 'nodes': nodes }, $html);
          }
        });
      });

      $browser_listing.on('click',function (e) {
        if ($(e.target).is($(this)) || $(e.target).is($('.media-listing'))) {
          $(this).find('.media-item').removeClass('selected');
        }

        Drupal.MediaBrowser.toolbar.selectionChanged();
      });
    }
  };

})(jQuery, Drupal);
