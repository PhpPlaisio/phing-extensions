<?php
declare(strict_types=1);

/**
 * Interface for source handlers.
 */
interface SourceHelper
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * PhpSourceHelperJs constructor.
   *
   * @param WebPackerInterface $parent The parent object.
   */
  public function __construct(WebPackerInterface $parent);

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Derives the type of a source file.  Returns the content type or null if it is not up to this class the handle the
   * given content.
   *
   * @param string $content The content of the source.
   *
   * @return bool
   */
  public static function deriveType(string $content): bool;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Analyze a source for any references to resources.
   *
   * @param array $source The details of the source.
   */
  public function analyze(array $source): void;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Processes a source and returns the modified source. References to resources must be replaced with references to
   * the optimized and bundled resources.
   *
   * @param array   $source    The details of the source.
   * @param array[] $resources The details of all resources referenced by the source.
   *
   * @return string|null
   */
  public function process(array $source, array $resources): ?string;

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
