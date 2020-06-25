<?php
declare(strict_types=1);

/**
 * Abstract parent class for tasks for optimizing resources
 */
abstract class ResourceStoreTask extends \PlaisioTask implements \WebPackerInterface
{
  //--------------------------------------------------------------------------------------------------------------------
  use WebPackerTrait;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute Brotli.
   *
   * @param bool $brotliFlag If set static Brotli compressed files of the optimized/minimized resources will be created.
   */
  public function setBrotli(bool $brotliFlag = false): void
  {
    $this->brotliFlag = $brotliFlag;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute BrotliPath.
   *
   * @param string $brotliPath Path to the Brotli program.
   */
  public function setBrotliPath(string $brotliPath): void
  {
    $this->brotliPath = $brotliPath;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute parentResourceDir.
   *
   * @param string $buildDir The path to the build dir.
   */
  public function setBuildDir(string $buildDir)
  {
    $this->buildPath = realpath($buildDir);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute cssCombineCommand.
   *
   * @param string $cssMinifyCommand The command to run csso.
   */
  public function setCssMinifyCommand(string $cssMinifyCommand): void
  {
    $this->cssMinifyCommand = $cssMinifyCommand;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute $cssMinimize.
   *
   * @param bool $cssMinimize
   */
  public function setCssMinimize(bool $cssMinimize): void
  {
    $this->cssMinimize = $cssMinimize;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute GZip.
   *
   * @param $gzipFlag bool
   */
  public function setGzip(bool $gzipFlag = false): void
  {
    $this->gzipFlag = $gzipFlag;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute jsCombineCommand.
   *
   * @param string $jsCombineCommand The command to run r.js.
   */
  public function setJsCombineCommand(string $jsCombineCommand): void
  {
    $this->jsCombineCommand = $jsCombineCommand;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute jsCombineCommand.
   *
   * @param string $jsMinifyCommand The command to run r.js.
   */
  public function setJsMinifyCommand(string $jsMinifyCommand): void
  {
    $this->jsMinifyCommand = $jsMinifyCommand;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute nodePath.
   *
   * @param string $jsNodePath The command to run r.js.
   */
  public function setJsNodePath(string $jsNodePath): void
  {
    $this->jsNodePath = $jsNodePath;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute jsRequirePath.
   *
   * @param string $jsRequirePath The command to run r.js.
   */
  public function setJsRequireJsPath(string $jsRequirePath): void
  {
    $this->jsRequirePath = $jsRequirePath;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute parentResourceDir.
   *
   * @param string $parentResourceDir The path to the resource dir.
   */
  public function setParentResourceDir(string $parentResourceDir)
  {
    $this->parentResourceDir = $parentResourceDir;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute preserveLastModified.
   *
   * @param $preserveLastModifiedFlag bool
   */
  public function setPreserveLastModified(bool $preserveLastModifiedFlag): void
  {
    $this->preserveModificationTime = $preserveLastModifiedFlag;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute resource.
   *
   * @param string $resources The ID of the fileset with resource files.
   */
  public function setResources(string $resources)
  {
    $this->resourcesFilesetId = $resources;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute sources.
   *
   * @param string $sources The ID of the fileset with source files.
   */
  public function setSources(string $sources): void
  {
    $this->sourcesFilesetId = $sources;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XMl attribute storeFilename.
   *
   * @param string|null $storeFilename
   */
  public function setStoreFilename(?string $storeFilename): void
  {
    $this->storeFilename = $storeFilename;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute webAssetsClasses.
   *
   * @param string $webAssetsClasses A space separated list of the web asset classes, interfaces and traits.
   */
  public function setWebAssetsClasses(string $webAssetsClasses): void
  {
    $this->webAssetsClasses = explode(' ', $webAssetsClasses);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Get info about all source, resource files and directories.
   */
  protected function prepareProjectData(): void
  {
    // Get full path name of resource dir.
    $resources                = $this->getProject()->getReference($this->resourcesFilesetId);
    $this->parentResourcePath = realpath($resources->getDir($this->getProject()).'/'.$this->parentResourceDir);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Sets the mode of a file.
   *
   * @param string $destinationFilename The full file name of destination file.
   * @param string $referenceFilename
   *
   * @throws \BuildException
   */
  protected function setFilePermissions(string $destinationFilename, string $referenceFilename): void
  {
    clearstatcache();
    $perms = fileperms($referenceFilename);
    if ($perms===false) $this->logError("Unable to get permissions of file '%s'", $referenceFilename);

    $status = chmod($destinationFilename, $perms);
    if ($status===false) $this->logError("Unable to set permissions for file '%s'", $destinationFilename);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Copy the last modification time of a file.
   *
   * @param string $path  The path of the file.
   * @param int    $mtime The  last modification time.
   *
   * @throws \BuildException
   */
  protected function setModificationTime(string $path, int $mtime): void
  {
    if ($this->preserveModificationTime)
    {
      $status = touch($path, $mtime);
      if ($status===false)
      {
        $this->logError("Unable to set mtime of file '%s'", $path);
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
