(function ($, Drupal, drupalSettings) {
  Drupal.MediaBrowser = {
    initialized: false,
    treeSelector: '#jstree-container',
    activeDirectory: -1,
    selectedMedia: [],
    targetBundles: [],
    cardinality: -1,
    urls: {}
  };

  /**
   * Main init function.
   */
  Drupal.MediaBrowser.init = function () {
    if (this.initialized) {
      return;
    }

    // Init tree.
    Drupal.MediaBrowser.tree();

    if ('media_directories' in drupalSettings) {
      if ('target_bundles' in drupalSettings.media_directories) {
        this.targetBundles = drupalSettings.media_directories.target_bundles;
      }

      if ('cardinality' in drupalSettings.media_directories) {
        this.cardinality = drupalSettings.media_directories.cardinality;
      }

    }

    Drupal.MediaBrowser.toolbar.init();

    // Mark media browser as initialized.
    this.initialized = true;
  };

  Drupal.MediaBrowser.getUrl = function (action_name) {
    if ('media_directories' in drupalSettings) {
      if (action_name in drupalSettings.media_directories.url) {
        return drupalSettings.media_directories.url[action_name];
      }
    }
  };

  Drupal.MediaBrowser.clearMediaSelection = function () {
    // Clear selection from global storage.
    Drupal.MediaBrowser.selectedMedia = [];
    Drupal.MediaBrowser.toolbar.selectionChanged();
  };

  Drupal.MediaBrowser.getSelectedElements = function () {
    const $browser_listing = $('.browser--listing');
    let elements = [];

    $.each(Drupal.MediaBrowser.selectedMedia, function (key, value) {
      let media_element = $('[data-mid="' + value + '"]', $browser_listing);
      if (media_element.length > 0) {
        elements.push(media_element);
      }
    });

    return elements;
  };

  Drupal.MediaBrowser.startLoader = function () {
    $('.browser').css('opacity', '0.5');
    $('.browser').css('pointer-events', 'none');
  };

  Drupal.MediaBrowser.stopLoader = function () {
    $('.browser').css('opacity', '1');
    $('.browser').css('pointer-events', 'auto');
  }

})(jQuery, Drupal, drupalSettings);
