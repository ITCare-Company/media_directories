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
          Drupal.MediaBrowser.media.ctrlPressed = true;
        }
      });

      $(top.document, document).on('keyup', function () {
        Drupal.MediaBrowser.media.ctrlPressed = false;
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
            Drupal.MediaBrowser.clearMediaSelection();
          }

          $(this).toggleClass('selected');
          let checkbox = $(this).find('input[type="checkbox"]');
          checkbox.prop("checked", !checkbox.prop("checked"));
          // Push media id to selection array.
          Drupal.MediaBrowser.selectedMedia.push($(this).data('mid'));
          Drupal.MediaBrowser.toolbar.selectionChanged();
        });

        $(this).draggable({
          revert: true,
          helper: 'clone',
          start: function (e) {
            let nodes = [];

            $.each(Drupal.MediaBrowser.getSelectedElements(), function (key, value) {
              nodes.push({ id : $(value).data('mid'), element: $(value) });
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
    }
  };

})(jQuery, Drupal);
