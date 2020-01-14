(function ($, Drupal) {
  /**
   * Initialize Media Browser.
   *
   * @type {{attach: Drupal.behaviors.MediaDirectoriesUi.attach}}
   */
  Drupal.behaviors.MediaDirectoriesUi = {
    attach: function attach(context) {
      Drupal.MediaBrowser.init();
      Drupal.MediaBrowser.media.init();
    }
  };
})(jQuery, Drupal);