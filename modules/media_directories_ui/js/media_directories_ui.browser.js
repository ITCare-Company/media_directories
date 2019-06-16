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

  /**
   * Get action url from settings.
   *
   * @param action_name
   * @returns {*}
   */
  Drupal.MediaBrowser.getUrl = function (action_name) {
    if ('media_directories' in drupalSettings) {
      if (action_name in drupalSettings.media_directories.url) {
        return drupalSettings.media_directories.url[action_name];
      }
    }
  };

  /**
   * Clear media from selection.
   * Some of the actions will change list of media items
   * so we might need to clear selected items.
   */
  Drupal.MediaBrowser.clearMediaSelection = function () {
    // Clear selection from global storage.
    Drupal.MediaBrowser.selectedMedia = [];
    // Notify toolbar of these changes.
    Drupal.MediaBrowser.toolbar.selectionChanged();
  };

  /**
   * Get DOM elements which are selected.
   * State only stores media ID's, because DOM will change and
   * we need to restore selection in some cases.
   *
   * @returns {Array}
   */
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

  /**
   * Start blocking user gestures and show that something is in progress.
   */
  Drupal.MediaBrowser.startLoader = function () {
    let $browser = $('.browser');
    $browser.css('opacity', '0.5');
    $browser.css('pointer-events', 'none');
  };

  /**
   * Unlock the UI so user can start interacting again.
   */
  Drupal.MediaBrowser.stopLoader = function () {
    let $browser = $('.browser');
    $browser.css('opacity', '1');
    $browser.css('pointer-events', 'auto');
  }

})(jQuery, Drupal, drupalSettings);
