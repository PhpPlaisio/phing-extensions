<?php
declare(strict_types=1);

namespace Plaisio\Phing\Task\WebPacker\ResourceHelper;

use Plaisio\Phing\Task\WebPacker\WebPackerInterface;

/**
 * Interface for resource handlers.
 */
interface ResourceHelper
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   *
   * @param WebPackerInterface $parent The parent object.
   */
  public function __construct(WebPackerInterface $parent);

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Derives the type of a resource file.  Returns the content type or null if it is not up to this class the handle the
   * given content.
   *
   * @param string $content The content of the resource.
   *
   * @return bool
   */
  public static function deriveType(string $content): bool;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns true if and only if a pre-compressed version of the resource must be generated.
   *
   * @return bool
   */
  public static function mustCompress(): bool;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Analyze a resource for any references to resources.
   *
   * @param array $resource1 The details of the resource.
   */
  public function analyze(array $resource1): void;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Optimizes a resource and returns the content of the optimized resource.
   *
   * @param array   $resource  The details of the resource.
   * @param array[] $resources The details of all resources referenced by the resource.
   *
   * @return string|null
   */
  public function optimize(array $resource, array $resources): ?string;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the URI for the optimized resource.
   *
   * @param array $resource The details of the resource.
   *
   * @return string|null
   */
  public function uriOptimizedPath(array $resource): ?string;

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
