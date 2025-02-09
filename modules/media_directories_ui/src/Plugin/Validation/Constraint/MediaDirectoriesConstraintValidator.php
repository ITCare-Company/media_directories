<?php

namespace Drupal\media_directories_ui\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\file\Plugin\Validation\Constraint\BaseFileConstraintValidator;
use Drupal\file\Validation\FileValidatorInterface;
use Drupal\media_directories_ui\MediaDirectoriesUiHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates the file with validators specified for it's type.
 */
class MediaDirectoriesConstraintValidator extends BaseFileConstraintValidator implements ContainerInjectionInterface {

  /**
   * The UI helper.
   *
   * @var \Drupal\media_directories_ui\MediaDirectoriesUiHelper
   */
  protected $uiHelper;

  /**
   * The file validator.
   *
   * @var \Drupal\file\Validation\FileValidatorInterface
   */
  protected $fileValidator;

  /**
   * Constructs a new OEmbedResourceConstraintValidator.
   *
   * @param \Drupal\media_directories_ui\MediaDirectoriesUiHelper $ui_helper
   *   The UI helper.
   * @param \Drupal\file\Validation\FileValidatorInterface $file_validator
   *   The file validator.
   */
  public function __construct(MediaDirectoriesUiHelper $ui_helper, FileValidatorInterface $file_validator) {
    $this->uiHelper = $ui_helper;
    $this->fileValidator = $file_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('media_directories_ui.helper'), $container->get('file.validator'));
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    $file = $this->assertValueIsFile($value);
    if (!$constraint instanceof MediaDirectoriesConstraint) {
      throw new UnexpectedTypeException($constraint, MediaDirectoriesConstraint::class);
    }

    /** @var \Drupal\media\Entity\MediaType $media_type */
    $media_type = $this->uiHelper->getMediaType($file);
    if ($media_type != NULL) {
      /** @var \Symfony\Component\Validator\ConstraintViolationListInterface $violations */
      $violations = $this->fileValidator->validate($file, $constraint->validatorsByMediaType[$media_type->id()]);
      foreach ($violations as $violation) {
        $this->context->addViolation($violation, [
          '%files-allowed' => $this->uiHelper->getValidExtensions(),
        ]);
      }
    }
    else {
      $this->context->addViolation($constraint->extensionNotAllowed);
    }
  }

}
