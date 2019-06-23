<?php

namespace Drupal\media_directories_ui\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\RendererInterface;
use Drupal\media_directories_ui\Form\MediaEditForm;
use Drupal\taxonomy\Entity\Term;
use Drupal\media_directories_ui\Ajax\RefreshDirectoryTree;
use Drupal\media_directories_ui\Form\DirectoryDeleteForm;
use Drupal\media_directories_ui\Form\MediaDeleteForm;
use Drupal\media_directories_ui\Ajax\LoadDirectoryContent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class MediaDirectoriesController extends ControllerBase {
  /**
   * @var \Drupal\taxonomy\TermStorage
   */
  protected $termStorage;

  /**
   * @var FormBuilder
   */
  protected $formBuilder;

  /**
   * @var RendererInterface
   */
  protected $renderer;

  /**
   * The vocabulary id to use.
   *
   * @var string
   */
  protected $vocabulary_id;

  /**
   * MediaDirectoriesController constructor.
   *
   * @param \Drupal\Core\Form\FormBuilder $formBuilder
   * @param \Drupal\Core\Render\RendererInterface $renderer
   */
  public function __construct(FormBuilder $formBuilder, RendererInterface $renderer) {
    $this->formBuilder = $formBuilder;
    $this->renderer = $renderer;

    $config = $this->config('media_directories.settings');
    $this->vocabulary_id = $config->get('directory_taxonomy');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('renderer')
    );
  }

  /**
   * Return directory tree as JSON.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function directoryTree() {
    $tree = [];
    $this->termStorage = $this->entityTypeManager()->getStorage('taxonomy_term');
    $terms = $this->termStorage->loadTree($this->vocabulary_id);

    foreach ($terms as $term) {
      $this->buildTree($tree, $term, $this->vocabulary_id);
    }

    $tree = [
      [
        'id' => 'dir-root',
        'text' => $this->t('Root'),
        'state' => [
          'opened' => TRUE,
          'selected' => TRUE,
        ],
        'a_attr' => [
          'data-tid' => MEDIA_DIRECTORY_ROOT,
        ],
        'children' => array_values($tree),
      ]
    ];

    return new JsonResponse($tree);
  }

  /**
   * Load directory content.
   *
   * Inserts directory content into browser.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public function directoryContent(Request $request) {
    $response = new AjaxResponse();
    $directory_id = (int)$request->request->get('directory_id');
    $target_bundles = $request->request->get('target_bundles');

    $bundles = $target_bundles ? implode('+', $target_bundles) : 'all';
    $view = views_embed_view('media_directory_browser', 'media_browser', $directory_id, $bundles);

    $response->addCommand(new HtmlCommand('.browser--listing', $view));

    return  $response;
  }

  /**
   * Create new directory.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function directoryAdd(Request $request) {
    $directory_id = (int)$request->request->get('parent_id');
    $directory_id = $directory_id === MEDIA_DIRECTORY_ROOT ? 0 : $directory_id;
    $name = $request->request->get('name');
    $directory = Term::create([
      'name' => $name,
      'vid' => $this->vocabulary_id,
      'parent' => [$directory_id],
    ]);
    $directory->save();

    $data = [
      'id' => 'dir-' . $directory->id(),
      'a_attr' => (object)['data-tid' => $directory->id()],
      'text' => $directory->getName(),
    ];

    return new JsonResponse($data);
  }

  /**
   * Rename directory.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function directoryRename(Request $request) {
    $directory_id = (int)$request->request->get('directory_id');
    $new_name = $request->request->get('directory_new_name');
    $directory = Term::load($directory_id);
    $directory->setName($new_name);
    $directory->save();

    $response = new AjaxResponse();

    return $response;
  }

  /**
   * Move directory to different directory.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function directoryMove(Request $request) {
    $response = new AjaxResponse();
    $move_directory_id = (int)$request->request->get('move_directory_id');
    $to_directory_id = (int)$request->request->get('directory_id');
    $response->addCommand(new RefreshDirectoryTree($to_directory_id));

    // This shouldn't happen, but might cause issues when it does.
    if ($move_directory_id === $to_directory_id) {
      return $response;
    }

    /** @var Term $directory */
    $directory = $this->entityTypeManager()->getStorage('taxonomy_term')->load($move_directory_id);
    $directory->get('parent')->setValue($to_directory_id === MEDIA_DIRECTORY_ROOT ? NULL: $to_directory_id);
    $directory->save();

    return $response;
  }

  /**
   * Delete directory.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public function directoryDelete(Request $request) {
    $response = new AjaxResponse();

    $directory_id = (int)$request->request->get('directory_id');
    $target_bundles = $request->request->get('target_bundles');
    $directory = Term::load($directory_id);

    if ($directory === NULL) {
      return $response;
    }

    $context = [
      'directory' => $directory,
      'target_bundles' => $target_bundles,
    ];
    $form = $this->formBuilder->getForm(DirectoryDeleteForm::class, $context);
    $response->addCommand(new OpenModalDialogCommand($this->t('Delete directory @name', ['@name' => $directory->getName()]), $form, ['width' => '500']));

    return $response;
  }

  /**
   * New media entity add form.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function mediaAdd(Request $request) {
    $response = new AjaxResponse();
    $active_directory = (int) $request->get('active_directory', MEDIA_DIRECTORY_ROOT);
    $target_bundles = $request->get('target_bundles');
    /** @var \Drupal\media\Entity\MediaType[] $types */
    $types = $this->entityTypeManager()->getStorage('media_type')->loadMultiple();
    $type_keys = array_keys($types);
    $selected_type = $request->get('media_type', reset($type_keys));

    $build = [
      '#theme' => 'media_directories_add',
      '#selected_type' => $selected_type,
      '#active_directory' => $active_directory,
      '#target_bundles' => $target_bundles,
    ];

    $response->addCommand(new OpenModalDialogCommand($this->t('Add media'), $build, ['width' => '800']));

    return $response;
  }

  /**
   * Media entity edit form.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Form\EnforcedResponseException
   * @throws \Drupal\Core\Form\FormAjaxException
   */
  public function mediaEdit(Request $request) {
    $response = new AjaxResponse();
    $media_items = $request->request->get('media_items', []);
    $active_directory = (int) $request->request->get('active_directory', MEDIA_DIRECTORY_ROOT);
    $media_entities = $this->entityTypeManager()->getStorage('media')->loadMultiple($media_items);

    $form_state = new FormState();
    $form_state->set('media', $media_entities);
    $form_state->set('active_directory', $active_directory);

    $media_form = $this->formBuilder()->buildForm(MediaEditForm::class, $form_state);

    $response->addCommand(new OpenModalDialogCommand($this->t('Edit media'), $media_form, ['width' => '800']));

    return $response;
  }

  /**
   * Move media to directory.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function mediaMove(Request $request) {
    $response = new AjaxResponse();
    $media_items = $request->request->get('media_items', []);
    $directory_id = (int)$request->request->get('directory_id');

    /** @var \Drupal\media\Entity\Media $media_entities */
    $media_entities = $this->entityTypeManager()->getStorage('media')->loadMultiple($media_items);

    foreach ($media_entities as $media_entity) {
      if ($media_entity->hasField('directory')) {
        $media_entity->get('directory')->setValue($directory_id === MEDIA_DIRECTORY_ROOT ? NULL: $directory_id);
        $media_entity->save();
      }
    }

    $response->addCommand(new LoadDirectoryContent());

    return $response;
  }

  /**
   * Media entity delete confirmation form.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function mediaDelete(Request $request) {
    $response = new AjaxResponse();
    $media_items = $request->request->get('media_items', []);

    if (empty($media_items)) {
      return $response;
    }

    $media_entities = $this->entityTypeManager()->getStorage('media')->loadMultiple($media_items);

    $context = [
      'media_items' => $media_entities,
    ];

    $form = $this->formBuilder->getForm(MediaDeleteForm::class, $context);
    $response->addCommand(new OpenModalDialogCommand($this->t('Delete media'), $form, ['width' => '500']));

    return $response;
  }

  /**
   * Populates a tree array given a taxonomy term tree object.
   *
   * @param $tree
   * @param $object
   * @param $vocabulary
   */
  protected function buildTree(&$tree, $object, $vocabulary) {
    if ($object->depth !== 0) {
      return;
    }

    $tree[$object->tid] = $object;
    $tree[$object->tid]->children = [];
    $tree[$object->tid]->text = $object->name;
    $tree[$object->tid]->a_attr = [
      'data-tid' => $object->tid,
    ];
    $tree[$object->tid]->id = 'dir-' . $object->tid;
    $object_children = &$tree[$object->tid]->children;

    $children = $this->termStorage->loadChildren($object->tid);

    if (!$children) {
      return;
    }

    $child_tree_objects = $this->termStorage->loadTree($vocabulary, $object->tid);

    foreach ($children as $child) {
      foreach ($child_tree_objects as $child_tree_object) {
        if ($child_tree_object->tid === $child->id()) {
          $this->buildTree($object_children, $child_tree_object, $vocabulary);
        }
      }
    }

    $tree[$object->tid]->children = array_values($tree[$object->tid]->children);
  }

}
