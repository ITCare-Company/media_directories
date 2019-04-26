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

})(jQuery, Drupal, drupalSettings);
