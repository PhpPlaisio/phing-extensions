<?php
declare(strict_types=1);

use Webmozart\PathUtil\Path;

/**
 * Helper class for text resources.
 */
class TextResourceHelper implements ResourceHelper, WebPackerInterface
{
  //--------------------------------------------------------------------------------------------------------------------
  use WebPackerTrait;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * PhpSourceHelperJs constructor.
   *
   * @param \WebPackerInterface $parent The parent object.
   */
  public function __construct(\WebPackerInterface $parent)
  {
    $this->initWebPackerTrait($parent);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public static function deriveType(string $content): bool
  {
    unset($content);

    return true;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public static function mustCompress(): bool
  {
    return false;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public function analyze(array $resource): void
  {
    unset($content);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public function optimize(array $resource): ?string
  {
    // We assume that text files are already optimized.
    return $resource['rsr_content'];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public function uriOptimizedPath(array $resource): ?string
  {
    $md5       = md5($resource['rsr_content_optimized'] ?? '');
    $extension = Path::getExtension($resource['rsr_path']);

    return sprintf('/%s/%s.%s', 'css', $md5, $extension);
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
