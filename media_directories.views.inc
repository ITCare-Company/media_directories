<?php

/**
 * Implements hook_views_data_alter().
 */
function media_directories_views_data_alter(array &$data) {
  $data['media_field_data']['directory']['filter'] = [
    'id' => 'media_directory',
    'title' => t('Media directory filter')
  ];
}
