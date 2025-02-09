<?php

namespace Drupal\media_directories_ui\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Media directories validators by type constraint.
 */
#[Constraint(
  id: 'MediaDirectoriesValidatorsByType',
  label: new TranslatableMarkup('Media directories validator by type', [], ['context' => 'Validation']),
  type: 'file'
)]
class MediaDirectoriesConstraint extends SymfonyConstraint {

  /**
   * All validators per media type.
   *
   * @var array $validators_by_media_type
   */
  public array $validatorsByMediaType = [];

  /**
   * The message when file extension is not allowed.
   *
   * @var string
   */
  public string $extensionNotAllowed = "Only files with the following extensions are allowed: %files-allowed.";

}
