<?php

//----------------------------------------------------------------------------------------------------------------------
/**
 * Abstract parent class for optimizing/minimizing resources (i.e. CSS and JS files) and modifying references to those
 * resources.
 */
abstract class optimizeResourceTask extends Task
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * If set static gzipped files of the optimized/minimized resources will be created.
   *
   * @var bool
   */
  protected $myGzipFlag = false;

  /**
   * If set stop build on errors.
   *
   * @var bool
   */
  protected $myHaltOnError = true;

  /**
   * The count of resource files with the same hash. The key is the hash of the optimized resource file.
   *
   * @var int[string]
   */
  protected $myHashCount;

  /**
   * The path to the parent resource dir (relative to the build dir).
   *
   * @var string
   */
  protected $myParentResourceDir;

  /**
   * The absolute path to the parent resource dir.
   *
   * @var string
   */
  protected $myParentResourceDirFullPath;

  /**
   * If set the file permissions of optimized/minimized resource files will inherited from its originals file.
   *
   * @var bool
   */
  protected $myPreserveFilePermissions = true;

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
  protected $myPreserveModificationTime = false;

  /**
   * The phing project.
   *
   * @var Project
   */
  protected $myProject;

  /**
   * Map from the original references to resource files to new references (which includes a hash).
   *
   * @var array
   */
  protected $myReplacePairs;

  /**
   * The path of the resource dir (relative to the parent resource dir).
   *
   * @var string
   */
  protected $myResourceDir;

  /**
   * The absolute path to the resource dir.
   *
   * @var string
   */
  protected $myResourceDirFullPath;

  /**
   * The names of the resource files.
   *
   * @var array
   */
  protected $myResourceFileNames;

  /**
   * Info about resource files.
   *
   * @var array
   */
  protected $myResourceFilesInfo;

  /**
   * The ID of the fileset with resource files.
   *
   * @var string
   */
  protected $myResourcesFilesetId;

  /**
   * The names of the sources files.
   *
   * @var array
   */
  protected $mySourceFileNames;

  /**
   * Info about source files.
   *
   * @var array
   */
  protected $mySourceFilesInfo;

  /**
   * The ID of the fileset with sources.
   *
   * @var string
   */
  protected $mySourcesFilesetId;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Initialises this Phing task.
   */
  public function init()
  {
    $this->myProject = $this->getProject();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Main method of this Phing task.
   */
  public function main()
  {
    // Get base info about project.
    $this->prepareProjectData();

    // Get all info about source files.
    $this->getInfoSourceFiles();

    // Get all info about resource files.
    $this->getInfoResourceFiles();

    // Compress and rename files with hash.
    $this->processResourceFiles();

    // Prepare all place holders.
    $this->preparePlaceHolders();

    // Replace references to resource files with references to optimized/minimized resource files.
    $this->processingSourceFiles();

    // Create pre-compressed versions of the optimized/minimized resource files.
    if ($this->myGzipFlag) $this->gzipCompressOptimizedResourceFiles();

    // Remove original resource files that are optimized/minimized.
    $this->unlinkResourceFiles();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute GZip.
   *
   * @param $theGzipFlag bool.
   */
  public function setGzip($theGzipFlag = false)
  {
    $this->myGzipFlag = (boolean)$theGzipFlag;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute haltOnError.
   *
   * @param $theHaltOnError
   */
  public function setHaltOnError($theHaltOnError)
  {
    $this->myHaltOnError = (boolean)$theHaltOnError;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute parentResourceDir.
   *
   * @param $theParentResourceDir string The path to the resource dir.
   */
  public function setParentResourceDir($theParentResourceDir)
  {
    $this->myParentResourceDir = $theParentResourceDir;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute preserveLastModified.
   *
   * @param $thePreserveLastModifiedFlag bool
   */
  public function setPreserveLastModified($thePreserveLastModifiedFlag)
  {
    $this->myPreserveModificationTime = (boolean)$thePreserveLastModifiedFlag;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute preservePermissions.
   *
   * @param $thePreservePermissionsFlag bool
   */
  public function setPreservePermissions($thePreservePermissionsFlag)
  {
    $this->myPreserveFilePermissions = (boolean)$thePreservePermissionsFlag;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute resourceDir.
   *
   * @param $theResourceDir string The directory of the resource files relative tot the parent resource dir.
   */
  public function setResourceDir($theResourceDir)
  {
    $this->myResourceDir = $theResourceDir;
  }


  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute resource.
   *
   * @param $theResources string The ID of the fileset with resource files.
   */
  public function setResources($theResources)
  {
    $this->myResourcesFilesetId = $theResources;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute sources.
   *
   * @param $theSources string The ID of the fileset with source files.
   */
  public function setSources($theSources)
  {
    $this->mySourcesFilesetId = $theSources;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns an array with classes and namespaces defined in PHP code.
   *
   * @param string $thePhpCode The PHP code.
   *
   * @return array
   */
  protected function getClasses($thePhpCode)
  {
    $tokens = token_get_all($thePhpCode);

    $mode      = '';
    $namespace = '';
    $classes   = [];
    foreach ($tokens as $i => $token)
    {
      // If this token is the namespace declaring, then flag that the next tokens will be the namespace name
      if (is_array($token) && $token[0]==T_NAMESPACE)
      {
        $mode      = 'namespace';
        $namespace = '';
        continue;
      }

      // If this token is the class declaring, then flag that the next tokens will be the class name
      if (is_array($token) && $token[0]==T_CLASS)
      {
        $mode = 'class';
        continue;
      }

      // While we're grabbing the namespace name...
      if ($mode=='namespace')
      {
        // If the token is a string or the namespace separator...
        if (is_array($token) && in_array($token[0], [T_STRING, T_NS_SEPARATOR]))
        {
          //Append the token's value to the name of the namespace
          $namespace .= $token[1];
        }
        elseif (is_array($token) && $token[0]==T_WHITESPACE)
        {
          // Ignore whitespace.
        }
        elseif ($token===';')
        {
          // If the token is the semicolon, then we're done with the namespace declaration
          $mode = '';
        }
        elseif ($token==='{')
        {
          throw new LogicException('Bracketed syntax for name space not supported.');
        }
        else
        {
          throw new LogicException(sprintf("Unexpected token %s", print_r($token, true)));
        }
      }

      // While we're grabbing the class name...
      if ($mode=='class')
      {
        // If the token is a string, it's the name of the class
        if (is_array($token) && $token[0]==T_STRING)
        {
          // Store the token's value as the class name
          $classes[$token[2]] = ['namespace' => $namespace,
                                 'class'     => $token[1],
                                 'line'      => $token[2]];

          $mode = '';
        }
      }
    }

    return $classes;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Gets the path name relative to the parent resource directory of a resource file.
   *
   * @param $thePath string The full path name of resource file.
   *
   * @return string The path name relative to the parent resource directory.
   * @throws BuildException
   */
  protected function getPathInSources($thePath)
  {
    if (strncmp($thePath, $this->myParentResourceDirFullPath, strlen($this->myParentResourceDirFullPath))!=0)
    {
      throw new BuildException(sprintf("Resource file '%s' is not under resource dir '%s'.",
                                       $thePath,
                                       $this->myParentResourceDirFullPath));
    }

    return substr($thePath, strlen($this->myParentResourceDirFullPath));
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @throws BuildException
   */
  protected function logError()
  {
    $args   = func_get_args();
    $format = array_shift($args);

    foreach ($args as &$arg)
    {
      if (!is_scalar($arg)) $arg = var_export($arg, true);
    }

    if ($this->myHaltOnError) throw new BuildException(vsprintf($format, $args));
    else $this->log(vsprintf($format, $args), Project::MSG_ERR);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   */
  protected function logInfo()
  {
    $args   = func_get_args();
    $format = array_shift($args);

    foreach ($args as &$arg)
    {
      if (!is_scalar($arg)) $arg = var_export($arg, true);
    }

    $this->log(vsprintf($format, $args), Project::MSG_INFO);
  }


  //--------------------------------------------------------------------------------------------------------------------
  /**
   */
  protected function logVerbose()
  {
    $args   = func_get_args();
    $format = array_shift($args);

    foreach ($args as &$arg)
    {
      if (!is_scalar($arg)) $arg = var_export($arg, true);
    }

    $this->log(vsprintf($format, $args), Project::MSG_VERBOSE);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Optimizes/minimizes all resource files (in the resource file set).
   */
  abstract protected function optimizeResourceFile();

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * In PHP code replaces references to resource files (i.e. CSS or JS files) with references to the optimized versions
   * of the resource files.
   *
   * @param string $thePhpCode The PHP code.
   *
   * @return string The modified PHP code.
   */
  abstract protected function processPhpSourceFile($thePhpCode);

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Enhance all elements in {@link $this->myResourceFilesInfo} with an ordinal to prevent hash collisions. (In most
   * cases this ordinal will be 0.)
   *
   * @todo if content is equal reuse ordinal.
   */
  private function enhanceResourceFilesInfoWithOrdinal()
  {
    $this->myHashCount = [];

    foreach ($this->myResourceFilesInfo as &$file_info)
    {
      if (!isset($this->myHashCount[$file_info['hash']]))
      {
        $this->myHashCount[$file_info['hash']] = 0;
      }

      $file_info['ordinal'] = $this->myHashCount[$file_info['hash']];

      $this->myHashCount[$file_info['hash']]++;
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the permissions of a file.
   *
   * @param string $theFilename The name of the file.
   *
   * @throws BuildException
   * @return int
   */
  private function getFilePermissions($theFilename)
  {
    $perms = fileperms($theFilename);
    if ($perms===false) $this->logError("Unable to get permissions of file '%s'.", $theFilename);

    return $perms;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the full path with hash of an resource file.
   *
   * @param array $theFileInfo An element from {@link $myResourceFilesInfo}.
   *
   * @return string
   */
  private function getFullPathNameWithHash($theFileInfo)
  {
    $path_parts = pathinfo($theFileInfo['full_path_name']);

    $path = $this->myResourceDirFullPath;
    $path .= '/'.$theFileInfo['hash'].'.'.$theFileInfo['ordinal'].'.'.$path_parts['extension'];

    return $path;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Get info about each file in the fileset.
   */
  private function getInfoResourceFiles()
  {
    $this->logVerbose('Get resource files info.');

    $resource_dir = $this->myProject->getReference($this->myResourcesFilesetId)->getDir($this->myProject);
    foreach ($this->myResourceFileNames as $filename)
    {
      clearstatcache();

      $path      = $resource_dir.'/'.$filename;
      $full_path = realpath($path);

      $this->myResourceFilesInfo[$full_path] = ['filename_in_fileset'  => $filename,
                                                'full_path_name'       => $full_path,
                                                'path_name_in_sources' => $this->getPathInSources($full_path),
                                                'mode'                 => $this->getFilePermissions($full_path)];
    }

    $suc = ksort($this->myResourceFilesInfo);
    if ($suc===false) $this->logError("ksort failed.");
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   *  Gets full path for each source file in the source fileset.
   */
  private function getInfoSourceFiles()
  {
    $this->logVerbose('Get resource files info.');

    // Get the base dir of the sources.
    $dir = $this->myProject->getReference($this->mySourcesFilesetId)->getDir($this->myProject);

    foreach ($this->mySourceFileNames as $theFileName)
    {
      $filename                            = $dir.'/'.$theFileName;
      $real_path                           = realpath($filename);
      $this->mySourceFilesInfo[$real_path] = $filename;
    }

    $suc = ksort($this->mySourceFilesInfo);
    if ($suc===false) $this->logError("ksort failed.");
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the maximum mtime of a sources file and its referenced optimized/minimized resource files.
   *
   * @param $theSourceFilename string The name of the source file.
   * @param $theContent        string The content of the source file with renamed references to resource files.
   *
   * @return int The max mtime.
   */
  private function getMaxModificationTime($theSourceFilename, $theContent)
  {
    $times = [];

    $time = filemtime($theSourceFilename);
    if ($time===false) $this->logError("Unable to get mtime of file '%s'.", $theSourceFilename);
    $times[] = $time;

    $resource_files_info = $this->getResourceFilesInSource($theContent);
    foreach ($resource_files_info as $resource_file_info)
    {
      $time = filemtime($resource_file_info['full_path_name_with_hash']);
      if ($time===false) $this->logError("Unable to get mtime for file '%s'.", $resource_file_info);
      $times[] = $time;
    }

    return max($times);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns an array with info about resource files referenced from a source file.
   *
   * @param $theSourceFileContent string Content of updated source file.
   *
   * @return array
   */
  private function getResourceFilesInSource($theSourceFileContent)
  {
    $resource_files = [];
    foreach ($this->myResourceFilesInfo as $file_info)
    {
      if (strpos($theSourceFileContent, $file_info['path_name_in_sources_with_hash'])!==false)
      {
        $resource_files[] = $file_info;
      }
    }

    return $resource_files;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Compresses optimized/minimized resource files with gzip.
   */
  private function gzipCompressOptimizedResourceFiles()
  {
    $this->logInfo('Gzip compressing files.');

    foreach ($this->myResourceFilesInfo as $file_info)
    {
      $this->logVerbose("Gzip compressing file '%s' to '%s'.",
                        $file_info['full_path_name_with_hash'],
                        $file_info['full_path_name_with_hash'].'.gz');

      // Get data from the file.
      $data_opt = file_get_contents($file_info['full_path_name_with_hash']);
      if ($data_opt===false)
      {
        $this->logError("Can not read the file '%s' or file does not exist.", $file_info['full_path_name_with_hash']);
      }

      // Compress data with gzip
      $data_gzip = gzencode($data_opt, 9);
      if ($data_gzip===false)
      {
        $this->logError("Can not write the file '%s' or file does not exist.",
                        $file_info['full_path_name_with_hash'].'.gz');
      }

      if (strlen($data_gzip)<strlen($data_opt))
      {
        // Write data to the file.
        $status = file_put_contents($file_info['full_path_name_with_hash'].'.gz', $data_gzip);
        if ($status===false)
        {
          $this->logError("Unable to write to file '%s", $file_info['full_path_name_with_hash'].'.gz');
        }

        // If required preserve mtime.
        if ($this->myPreserveModificationTime)
        {
          $this->setModificationTime($file_info['full_path_name_with_hash'],
                                     $file_info['full_path_name_with_hash'].'.gz');
        }

        // If required preserve file permissions.
        if ($this->myPreserveModificationTime)
        {
          $this->setFilePermissions($file_info['full_path_name_with_hash'].'.gz', $file_info['mode']);
        }
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Prepares place holders with resource file names and references to optimized/minimized resource file names (with
   * includes a hash).
   */
  private function preparePlaceHolders()
  {
    $this->logVerbose('Prepare place holders.');

    foreach ($this->myResourceFilesInfo as $file_info)
    {
      $this->myReplacePairs["'".$file_info['path_name_in_sources']."'"] = "'".$file_info['path_name_in_sources_with_hash']."'";
      $this->myReplacePairs['"'.$file_info['path_name_in_sources'].'"'] = '"'.$file_info['path_name_in_sources_with_hash'].'"';

      if (isset($file_info['path_name_in_sources_alternative']))
      {
        $this->myReplacePairs["'".$file_info['path_name_in_sources_alternative']."'"] = "'".$file_info['path_name_in_sources_with_hash']."'";
        $this->myReplacePairs['"'.$file_info['path_name_in_sources_alternative'].'"'] = '"'.$file_info['path_name_in_sources_with_hash'].'"';
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Get info about all source, resource files and directories.
   */
  private function prepareProjectData()
  {
    $this->logVerbose('Get source and resource file names.');

    // Get file list form the project by fileset ID.
    $resources                 = $this->myProject->getReference($this->myResourcesFilesetId);
    $this->myResourceFileNames = $resources->getDirectoryScanner($this->myProject)->getIncludedFiles();

    // Get file list form the project by fileset ID.
    $sources                 = $this->myProject->getReference($this->mySourcesFilesetId);
    $this->mySourceFileNames = $sources->getDirectoryScanner($this->myProject)->getIncludedFiles();
    $suc                     = ksort($this->mySourceFileNames);
    if ($suc===false) $this->logError("ksort failed.");

    // Get full path name of resource dir.
    $this->myParentResourceDirFullPath = realpath($resources->getDir($this->myProject).'/'.$this->myParentResourceDir);

    // Get full path name of resource dir.
    $this->myResourceDirFullPath = realpath($this->myParentResourceDirFullPath.'/'.$this->myResourceDir);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Minimizes/optimizes the resource files and removes original resources files from the build dir.
   */
  private function processResourceFiles()
  {
    // Optimize/minimize all resource files.
    $this->optimizeResourceFile();

    // Enhance elements in $this->myResourceFilesInfo with an ordinal number to prevent hash collisions.
    $this->enhanceResourceFilesInfoWithOrdinal();

    // Save all optimized resource files.
    $this->saveOptimizedResourceFiles();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Replaces references to resource files in source files with the hashed filename op the optimized/minimized
   * resources.
   */
  private function processingSourceFiles()
  {
    $this->logVerbose('Replace references to resource files with references to optimized/minimized resource files.');

    foreach ($this->mySourceFilesInfo as $source_filename)
    {
      $content = file_get_contents($source_filename);
      if ($content===false) $this->logError("Unable to read file '%s'.", $source_filename);

      $new_content = $content;

      if (strncmp($content, '<?php', 5)===0)
      {
        // Source is a PHP file.
        $new_content = $this->processPhpSourceFile($new_content);
      }

      $new_content = strtr($new_content, $this->myReplacePairs);

      if ($content!=$new_content)
      {
        $time = null;

        // If required determine the latest modification time of the source file and its referenced resource files.
        if ($this->myPreserveModificationTime)
        {
          $time = $this->getMaxModificationTime($source_filename, $new_content);
        }

        // Write sources file with modified references to resource files.
        $status = file_put_contents($source_filename, $new_content);
        if ($status===false) $this->logError("Updating file '%s' failed.", $source_filename);
        $this->logInfo("Updated file '%s'.", $source_filename);

        // If required set the mtime to the latest modification time of the source file and its referenced resource
        // files.
        if ($this->myPreserveModificationTime)
        {
          $status = touch($source_filename, $time);
          if ($status===false) $this->logError("Unable to set mtime for file '%s'.", $source_filename);
        }
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Enhance all elements in {@link $this->myResourceFilesInfo} with an ordinal to prevent hash collisions. (In most
   * cases this ordinal will be 0.)
   */
  private function saveOptimizedResourceFiles()
  {
    $this->logInfo("Saving minimized files.");

    foreach ($this->myResourceFilesInfo as &$file_info)
    {
      $file_info['full_path_name_with_hash']       = $this->getFullPathNameWithHash($file_info);
      $file_info['path_name_in_sources_with_hash'] = $this->getPathInSources($file_info['full_path_name_with_hash']);

      $bytes = file_put_contents($file_info['full_path_name_with_hash'], $file_info['content_opt']);
      if ($bytes===false) $this->logError("Unable to write to file '%s'.", $file_info['full_path_name_with_hash']);

      // If required preserve mtime.
      if ($this->myPreserveModificationTime)
      {
        $this->setModificationTime($file_info['full_path_name'], $file_info['full_path_name_with_hash']);
      }

      // If required preserve file permissions.
      if ($this->myPreserveModificationTime)
      {
        $this->setFilePermissions($file_info['full_path_name_with_hash'], $file_info['mode']);
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Sets the mode of a file.
   *
   * @param string $theFilename The filename.
   * @param string $theMode     The file mode bits.
   *
   * @throws BuildException
   */
  private function setFilePermissions($theFilename, $theMode)
  {
    $status = chmod($theFilename, $theMode);
    if ($status===false) $this->logError("Unable to set permissions for file '%s'.", $theFilename);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Copy the mtime form the source file to the destination file.
   *
   * @param $theSourceFilename      string The full file name of source file.
   * @param $theDestinationFilename string The full file name of destination file.
   */
  private function setModificationTime($theSourceFilename, $theDestinationFilename)
  {
    $time = filemtime($theSourceFilename);
    if ($time===false) $this->logError("Unable to get mtime of file '%s'.", $theSourceFilename);

    $status = touch($theDestinationFilename, $time);
    if ($status===false)
    {
      $this->logError("Unable to set mtime of file '%s' to mtime of '%s",
                      $theDestinationFilename,
                      $theSourceFilename);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Removes resource files that have been optimized/minimized.
   */
  private function unlinkResourceFiles()
  {
    $this->logInfo("Removing resource files.");

    foreach ($this->myResourceFilesInfo as $file_info)
    {
      if (isset($file_info['full_path_name_with_hash']) && $file_info['full_path_name'])
      {
        // Resource file has an optimized/minimized version. Remove the original file.
        $this->logInfo("Removing '%s'.", $file_info['full_path_name']);
        unlink($file_info['full_path_name']);
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
