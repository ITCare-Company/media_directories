<?php

namespace Drupal\media_directories\Plugin\views\argument;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Media directory argument plugin.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("media_directory")
 */
class MediaDirectoryArgument extends ArgumentPluginBase {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The operator used for the query: or|and.
   * @var string
   */
  public $operator;

  /**
   * The actual value which is used for querying.
   * @var array
   */
  public $value;

  /**
   * Constructs a PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $configFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->definition = $plugin_definition + $configuration;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('config.factory'));
  }

  protected function defineOptions() {
    return parent::defineOptions();
  }

  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
  }

  public function title() {
    if (!$this->argument) {
      return !empty($this->definition['empty field name']) ? $this->definition['empty field name'] : $this->t('Uncategorized');
    }

    if (!empty($this->options['break_phrase'])) {
      $break = static::breakString($this->argument, FALSE);
      $this->value = $break->value;
      $this->operator = $break->operator;
    }
    else {
      $this->value = [$this->argument];
      $this->operator = 'or';
    }

    if (empty($this->value)) {
      return !empty($this->definition['empty field name']) ? $this->definition['empty field name'] : $this->t('Uncategorized');
    }

    if ($this->value === [MEDIA_DIRECTORY_ROOT]) {
      return !empty($this->definition['invalid input']) ? $this->definition['invalid input'] : $this->t('Invalid input');
    }

    return implode($this->operator == 'or' ? ' + ' : ', ', $this->titleQuery());
  }

  /**
   * Override for specific title lookups.
   * @return array
   *   Returns all titles, if it's just one title it's an array with one entry.
   */
  public function titleQuery() {
    return $this->value;
  }

  public function query($group_by = FALSE) {
    $this->ensureMyTable();
    $config = $this->configFactory->get('media_directories.settings');

    $this->value = (int)$this->argument;
    $placeholder = $this->placeholder();
    $null_check = empty($this->options['not']) ? '' : " OR $this->tableAlias.$this->realField IS NULL";

    if ($this->value === MEDIA_DIRECTORY_ROOT) {
      $group = 0;
      if ($config->get('all_files_in_root')) {
        // We want an exclusive group if we change to OR
        $group = 9;
        // Show everything.
        $this->query->setWhereGroup('OR', $group);
        $this->query->addWhereExpression($group, "$this->tableAlias.$this->realField IS NOT NULL");
      }
      $this->query->addWhereExpression($group, "$this->tableAlias.$this->realField IS NULL");
    }
    else {
      $operator = empty($this->options['not']) ? '=' : '!=';
      $this->query->addWhereExpression(0, "$this->tableAlias.$this->realField $operator $placeholder" . $null_check, [$placeholder => $this->argument]);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function getSortName() {
    return $this->t('Numerical', [], ['context' => 'Sort order']);
  }

  /**
   * {@inheritdoc}
   */
  public function getContextDefinition() {
    if ($context_definition = parent::getContextDefinition()) {
      return $context_definition;
    }

    // If the parent does not provide a context definition through the
    // validation plugin, fall back to the integer type.
    return new ContextDefinition('integer', $this->adminLabel(), FALSE);
  }

}
