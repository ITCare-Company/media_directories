(function ($, Drupal) {

  /**
   * Load active directory content.
   *
   * @param ajax
   * @param response
   * @param status
   */
  Drupal.AjaxCommands.prototype.loadDirectoryContent = function (ajax, response, status) {
    const $jsTree = $(Drupal.MediaBrowser.treeSelector);
    const target_bundles = Drupal.MediaBrowser.targetBundles;
    let active_element = $jsTree.jstree('get_selected', true);
    let active_tid = Drupal.MediaBrowser.activeDirectory;

    if (active_element.length > 0) {
      active_tid = active_element[0].a_attr["data-tid"];
    }

    let ajaxSettings = {
      url: Drupal.MediaBrowser.getUrl('directory.content'),
      submit: {
        directory_id: active_tid,
        target_bundles: target_bundles
      }
    };

    Drupal.MediaBrowser.startLoader();

    Drupal.ajax(ajaxSettings).execute().done(function () {
      Drupal.MediaBrowser.media.init($('.browser--listing'));
      $.each(Drupal.MediaBrowser.getSelectedElements(), function () {
        $(this).addClass('selected');
        $('input[type="checkbox"]', this).prop('checked', true);
      });

      Drupal.MediaBrowser.stopLoader();
    });
  }
})(jQuery, Drupal);
