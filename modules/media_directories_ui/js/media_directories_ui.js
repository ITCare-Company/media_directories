(function ($, Drupal) {

  Drupal.behaviors.MediaDirectoriesUi = {
    attach: function (context) {
      Drupal.MediaBrowser.init();
      Drupal.MediaBrowser.media.init();
    }
  }

})(jQuery, Drupal);
