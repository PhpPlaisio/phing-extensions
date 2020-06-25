<?php
declare(strict_types=1);

/**
 * Interface WebPackerInterface
 *
 * @property-read bool          $brotliFlag
 * @property-read string        $brotliPath
 * @property-read string        $buildPath
 * @property-read string        $cssMinifyCommand
 * @property-read bool          $cssMinimize
 * @property-read bool          $gzipFlag
 * @property-read string        $jsCombineCommand
 * @property-read string        $jsMinifyCommand
 * @property-read string        $jsNodePath
 * @property-read string        $jsRequirePath
 * @property-read string        $parentResourceDir
 * @property-read string        $parentResourcePath
 * @property-read bool          $preserveModificationTime
 * @property-read string        $resourcesFilesetId
 * @property-read string        $sourcesFilesetId
 * @property-read ResourceStore $store
 * @property-read string|null   $storeFilename
 * @property-read string[]      $webAssetsClasses
 * @property-read \PlaisioTask  $task
 */
interface WebPackerInterface
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Initializes the properties of this trait.
   *
   * @param \WebPackerInterface $parent
   */
  public function initWebPackerTrait(\WebPackerInterface $parent);

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
