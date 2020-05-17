<?php
declare(strict_types=1);

use SetBased\Exception\FallenException;

/**
 * Abstract parent class for tasks for optimizing resources (i.e. CSS and JS files). This class does the housekeeping
 * of resources.
 */
abstract class ResourceStoreTask extends \PlaisioTask
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * If set static Brotli compressed files of the optimized/minimized resources will be created.
   *
   * @var bool
   */
  protected $brotliFlag = false;

  /**
   * Path to the Brotli program.
   *
   * @var string
   */
  protected $brotliPath = 'brotli';

  /**
   * The extension of the resource files (i.e. .js or .css).
   *
   * @var string
   */
  protected $extension;

  /**
   * If set static gzipped files of the optimized/minimized resources will be created.
   *
   * @var bool
   */
  protected $gzipFlag = false;

  /**
   * The absolute path to the parent resource dir.
   *
   * @var string
   */
  protected $parentResourceDirFullPath;

  /**
   * If set
   * <ul>
   * <li> The mtime of optimized/minimized resource files will be inherited from its originals file.
   * <li> If two or more source files will be combined in a single resource file the mtime of this combined file will
   *      be set to the maximum mtime of the original resource files.
   * <li> When a PHP file is modified its mtime will be set to the maximum mtime of the PHP file and the referenced
   *      resource files.
   * </ul>
   *
   * @var bool
   */
  protected $preserveModificationTime = false;

  /**
   * The path of the resource dir (relative to the parent resource dir).
   *
   * @var string
   */
  protected $resourceDir;

  /**
   * The absolute path to the resource dir.
   *
   * @var string
   */
  protected $resourceDirFullPath;

  /**
   * The count of resource files with the same hash. The key is the hash of the optimized resource file.
   *
   * @var int[string]
   */
  private $hashCount;

  /**
   * The path to the parent resource dir (relative to the build dir).
   *
   * @var string
   */
  private $parentResourceDir;

  /**
   * The names of the resource files.
   *
   * @var array
   */
  private $resourceFileNames;

  /**
   * Array with information about file resources such as 'hash', 'content' etc.
   *
   * @var array
   */
  private $resourceFilesInfo;

  /**
   * The ID of the fileset with resource files.
   *
   * @var string
   */
  private $resourcesFilesetId;

  //--------------------------------------------------------------------------------------------------------------------

  /**
   * Object constructor.
   *
   * @param string $extension The extension of the resource files (i.e. .js or .css).
   */
  public function __construct(string $extension)
  {
    parent::__construct();

    $this->resourceFilesInfo = [];
    $this->extension         = $extension;
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
   * Setter for XML attribute resourceDir.
   *
   * @param string $resourceDir The directory of the resource files relative tot the parent resource dir.
   */
  public function setResourceDir(string $resourceDir)
  {
    $this->resourceDir = $resourceDir;
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
   * Returns the full path with hash of an resource file.
   *
   * @param array $fileInfo An element from {@link $resourceFilesInfo}.
   *
   * @return string
   */
  protected function getFullPathNameWithHash(array $fileInfo): string
  {
    $path = $this->resourceDirFullPath;
    $path .= '/'.$fileInfo['hash'].'.'.$fileInfo['ordinal'].$this->extension;

    return $path;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns an array with all hashed resources.
   *
   * @return array
   */
  protected function getHashedResourceFilenames(): array
  {
    $paths = [];
    foreach ($this->getResourcesInfo() as $info)
    {
      $paths[] = $info['full_path_name_with_hash'];
    }

    return array_unique($paths, SORT_STRING);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Get info about each file in the fileset.
   */
  protected function getInfoResourceFiles(): void
  {
    $this->logVerbose('Get resource files info');

    $resource_dir = $this->getProject()->getReference($this->resourcesFilesetId)->getDir($this->getProject());

    foreach ($this->resourceFileNames as $filename)
    {
      clearstatcache();

      $path      = $resource_dir.'/'.$filename;
      $full_path = realpath($path);

      $this->store(file_get_contents($full_path), $full_path, $full_path, null);
    }

    $suc = ksort($this->resourceFilesInfo);
    if ($suc===false) $this->logError("ksort failed");
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Gets the path name relative to the parent resource directory of a resource file.
   *
   * @param $path string The full path name of resource file.
   *
   * @return string The path name relative to the parent resource directory.
   *
   * @throws \BuildException
   */
  protected function getPathInResources(string $path): string
  {
    if (strncmp($path, $this->parentResourceDirFullPath, strlen($this->parentResourceDirFullPath))!=0)
    {
      throw new \BuildException(sprintf("Resource file '%s' is not under resource dir '%s'",
                                        $path,
                                        $this->parentResourceDirFullPath));
    }

    return substr($path, strlen($this->parentResourceDirFullPath));
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns path name in sources with hash from the resource info based on the path name in sources.
   * If can't find, return path name in sources.
   *
   * @param string $baseUrl          Parent resource folder.
   * @param string $resourcePathName Path name to the resource.
   *
   * @return string
   */
  protected function getPathInResourcesWithHash(string $baseUrl, string $resourcePathName): string
  {
    foreach ($this->resourceFilesInfo as $info)
    {
      if ($info['path_name_in_sources']===$baseUrl.'/'.$resourcePathName.$this->extension)
      {
        return $info['path_name_in_sources_with_hash'];
      }
    }

    return $resourcePathName;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the resource info based on the full path of the resource.
   *
   * @param string $fullPathName
   *
   * @return array|null
   *
   * @throws \BuildException
   */
  protected function getResourceInfo(string $fullPathName): ?array
  {
    foreach ($this->resourceFilesInfo as $info)
    {
      if ($info['full_path_name']===$fullPathName)
      {
        return $info;
      }
    }

    $this->logError("Unknown resource file '%s'", $fullPathName);

    return null;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the resource info based on the full path of the resource.
   *
   * @param $fullPathNameWithHash
   *
   * @return array
   *
   * @throws \BuildException
   */
  protected function getResourceInfoByHash($fullPathNameWithHash)
  {
    foreach ($this->resourceFilesInfo as $info)
    {
      if ($info['full_path_name_with_hash']===$fullPathNameWithHash)
      {
        return $info;
      }
    }

    $this->logError("Unknown resource file '%s'", $fullPathNameWithHash);

    return null;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns an array with info about all resources.
   *
   * @return array
   */
  protected function getResourcesInfo(): array
  {
    return $this->resourceFilesInfo;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Minimizes JavaScript or CSS code.
   *
   * @param string      $resource     The JavaScript or CSS code.
   * @param string|null $fullPathName The full pathname of the JavaScript or CSS file.
   *
   * @return string The minimized JavaScript or CSS code.
   */
  abstract protected function minimizeResource(string $resource, ?string $fullPathName): string;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Get info about all source, resource files and directories.
   */
  protected function prepareProjectData(): void
  {
    $this->logVerbose('Get source and resource file names');

    // Get file list form the project by fileset ID.
    $resources               = $this->getProject()->getReference($this->resourcesFilesetId);
    $this->resourceFileNames = $resources->getDirectoryScanner($this->getProject())->getIncludedFiles();

    // Get full path name of resource dir.
    $this->parentResourceDirFullPath = realpath($resources->getDir($this->getProject()).'/'.$this->parentResourceDir);

    // Get full path name of resource dir.
    $this->resourceDirFullPath = realpath($this->parentResourceDirFullPath.'/'.$this->resourceDir);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Enhance all elements in {@link $this->myResourceFilesInfo} with an ordinal to prevent hash collisions. (In most
   * cases this ordinal will be 0.)
   *
   * @throws \BuildException
   */
  protected function saveOptimizedResourceFiles(): void
  {
    $this->logInfo("Saving minimized files");

    foreach ($this->resourceFilesInfo as $file_info)
    {
      $file_info['full_path_name_with_hash']       = $this->getFullPathNameWithHash($file_info);
      $file_info['path_name_in_sources_with_hash'] = $this->getPathInResources($file_info['full_path_name_with_hash']);

      $bytes = file_put_contents($file_info['full_path_name_with_hash'], $file_info['content_opt']);
      if ($bytes===false) $this->logError("Unable to write to file '%s'", $file_info['full_path_name_with_hash']);

      if (isset($file_info['full_path_name']))
      {
        // If required preserve mtime.
        if ($this->preserveModificationTime)
        {
          $status = touch($file_info['full_path_name_with_hash'], $file_info['mtime']);
          if ($status===false)
          {
            $this->logError("Unable to set mtime of file '%s'", $file_info['full_path_name_with_hash']);
          }
        }
      }
    }
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
   * Copy the mtime form the source file to the destination file.
   *
   * @param string $destinationFilename The full file name of destination file.
   * @param int    $newMtime            The new Mtime.
   *
   * @throws \BuildException
   */
  protected function setModificationTime(string $destinationFilename, int $newMtime): void
  {
    $status = touch($destinationFilename, $newMtime);
    if ($status===false)
    {
      $this->logError("Unable to set mtime of file '%s'", $destinationFilename);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Minimize resource, create hash based on optimized content. Add resource info into array.
   *
   * @param string       $resource     The (actual content) of the resource.
   * @param string|null  $fullPathName The full pathname of the file where the resource is stored.
   * @param string|array $parts        Array with original resource files.
   * @param string|null  $getInfoBy    Flag for look in source with hash or without
   *
   * @return array
   */
  protected function store(string $resource, ?string $fullPathName, $parts, ?string $getInfoBy): array
  {
    if ($fullPathName!==null) $this->logInfo("Minimizing '%s'", $fullPathName);

    $content_opt = $this->minimizeResource($resource, $fullPathName);

    // @todo Ignore *.main.js files.

    $file_info                                   = [];
    $file_info['hash']                           = md5($content_opt);
    $file_info['content_raw']                    = $resource;
    $file_info['content_opt']                    = $content_opt;
    $file_info['ordinal']                        = isset($this->hashCount[$file_info['hash']]) ? $this->hashCount[$file_info['hash']]++ : $this->hashCount[$file_info['hash']] = 0;
    $file_info['full_path_name_with_hash']       = $this->resourceDirFullPath.'/'.
      $file_info['hash'].'.'.$file_info['ordinal'].$this->extension;
    $file_info['path_name_in_sources_with_hash'] = $this->getPathInResources($file_info['full_path_name_with_hash']);

    if ($fullPathName!==null)
    {
      $file_info['full_path_name']       = $fullPathName;
      $file_info['path_name_in_sources'] = $this->getPathInResources($fullPathName);
    }

    if ($parts!==null)
    {
      $file_info['mtime'] = $this->getMaxMtime($parts, $getInfoBy);
    }

    $this->resourceFilesInfo[] = $file_info;

    return $file_info;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Removes resource files that have been optimized/minimized.
   */
  protected function unlinkResourceFiles(): void
  {
    $this->logInfo("Removing resource files");
    $count = 0;

    foreach ($this->resourceFilesInfo as $file_info)
    {
      if (isset($file_info['full_path_name_with_hash']) && isset($file_info['full_path_name']))
      {
        // Resource file has an optimized/minimized version. Remove the original file.
        if (file_exists($file_info['full_path_name']))
        {
          $this->logVerbose("Removing '%s'", $file_info['full_path_name']);
          unlink($file_info['full_path_name']);
          $count++;
        }
      }
    }

    $this->logInfo("Removed %d resource files", $count);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Return mtime if $theParts is one file or return max mtime if array
   *
   * @param array|string $parts
   * @param string|null  $getInfoBy Flag for look in source with hash or without hash.
   *
   * @return int mtime
   */
  private function getMaxMtime($parts, ?string $getInfoBy): int
  {
    $mtime = [];
    if (is_array($parts))
    {
      foreach ($parts as $part)
      {
        switch ($getInfoBy)
        {
          case 'full_path_name_with_hash':
            $info = $this->getResourceInfoByHash($part);
            break;

          case 'full_path_name':
            $info = $this->getResourceInfo($part);
            break;

          default:
            throw new FallenException('getInfoBy', $getInfoBy);
        }
        $mtime[] = $info['mtime'];
      }
    }
    else
    {
      $mtime[] = filemtime($parts);
    }

    return max($mtime);
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------