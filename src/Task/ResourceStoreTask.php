<?php
declare(strict_types=1);

use SetBased\Exception\FallenException;

/**
 * Abstract parent class for tasks for optimizing resources (i.e. CSS and JS files). This class does the housekeeping
 * of resources.
 */
abstract class ResourceStoreTask extends \Task
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
   * If set stop build on errors.
   *
   * @var bool
   */
  private $haltOnError = true;

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
  public function __construct($extension)
  {
    $this->resourceFilesInfo = [];
    $this->extension         = $extension;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute haltOnError.
   *
   * @param $haltOnError
   */
  public function setHaltOnError($haltOnError)
  {
    $this->haltOnError = (boolean)$haltOnError;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute parentResourceDir.
   *
   * @param $parentResourceDir string The path to the resource dir.
   */
  public function setParentResourceDir($parentResourceDir)
  {
    $this->parentResourceDir = $parentResourceDir;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute resourceDir.
   *
   * @param $resourceDir string The directory of the resource files relative tot the parent resource dir.
   */
  public function setResourceDir($resourceDir)
  {
    $this->resourceDir = $resourceDir;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute resource.
   *
   * @param $resources string The ID of the fileset with resource files.
   */
  public function setResources($resources)
  {
    $this->resourcesFilesetId = $resources;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the full path with hash of an resource file.
   *
   * @param array $fileInfo An element from {@link $myResourceFilesInfo}.
   *
   * @return string
   */
  protected function getFullPathNameWithHash($fileInfo)
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
  protected function getInfoResourceFiles()
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
   * @throws \BuildException
   */
  protected function getPathInResources($path)
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
  protected function getPathInResourcesWithHash($baseUrl, $resourcePathName)
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
   * @param $fullPathName
   *
   * @return array
   * @throws BuildException
   */
  protected function getResourceInfo($fullPathName)
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
   * @throws BuildException
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
  protected function getResourcesInfo()
  {
    return $this->resourceFilesInfo;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Prints an error message and depending on HaltOnError throws an exception.
   *
   * @param mixed ...$param The arguments as for [sprintf](http://php.net/manual/function.sprintf.php)
   *
   * @throws \BuildException
   */
  protected function logError()
  {
    $args   = func_get_args();
    $format = array_shift($args);

    foreach ($args as &$arg)
    {
      if (!is_scalar($arg)) $arg = var_export($arg, true);
    }

    if ($this->haltOnError) throw new \BuildException(vsprintf($format, $args));

    if (sizeof($args)==0)
    {
      $this->log($format, \Project::MSG_ERR);
    }
    else
    {
      $this->log(vsprintf($format, $args), \Project::MSG_ERR);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Prints an info message.
   *
   * @param mixed ...$param The arguments as for [sprintf](http://php.net/manual/function.sprintf.php)
   */
  protected function logInfo()
  {
    $args   = func_get_args();
    $format = array_shift($args);

    foreach ($args as &$arg)
    {
      if (!is_scalar($arg)) $arg = var_export($arg, true);
    }

    if (sizeof($args)==0)
    {
      $this->log($format, \Project::MSG_INFO);
    }
    else
    {
      $this->log(vsprintf($format, $args), \Project::MSG_INFO);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Prints an verbose level message.
   *
   * @param mixed ...$param The arguments as for [sprintf](http://php.net/manual/function.sprintf.php)
   */
  protected function logVerbose()
  {
    $args   = func_get_args();
    $format = array_shift($args);

    foreach ($args as &$arg)
    {
      if (!is_scalar($arg)) $arg = var_export($arg, true);
    }

    if (sizeof($args)==0)
    {
      $this->log($format, \Project::MSG_VERBOSE);
    }
    else
    {
      $this->log(vsprintf($format, $args), \Project::MSG_VERBOSE);
    }
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
  protected function prepareProjectData()
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
   * @throws BuildException
   */
  protected function saveOptimizedResourceFiles()
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
   * @param $destinationFilename string The full file name of destination file.
   * @param $referenceFilename
   *
   * @throws BuildException
   */
  protected function setFilePermissions($destinationFilename, $referenceFilename)
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
   * @param $destinationFilename string The full file name of destination file.
   * @param $newMtime
   *
   * @throws BuildException
   */
  protected function setModificationTime($destinationFilename, $newMtime)
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
   * @param string       $getInfoBy    Flag for look in source with hash or without
   *
   * @return array
   */
  protected function store($resource, $fullPathName, $parts, $getInfoBy)
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

    if (isset($fullPathName))
    {
      $file_info['full_path_name']       = $fullPathName;
      $file_info['path_name_in_sources'] = $this->getPathInResources($fullPathName);
    }

    if (isset($parts))
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
  protected function unlinkResourceFiles()
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
   * @param string       $getInfoBy Flag for look in source with hash or without hash.
   *
   * @return int mtime
   */
  private function getMaxMtime($parts, $getInfoBy)
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
            throw new FallenException('$theGetInfoBy', $getInfoBy);
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
