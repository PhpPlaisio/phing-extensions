<?php
//----------------------------------------------------------------------------------------------------------------------
/**
 * Abstract parent class for tasks for optimizing resources (i.e. CSS and JS files). This class does the housekeeping
 * op resources.
 */
abstract class ResourceStoreTask extends \Task
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The absolute path to the parent resource dir.
   *
   * @var string
   */
  protected $myParentResourceDirFullPath;

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
   * If set stop build on errors.
   *
   * @var bool
   */
  private $myHaltOnError = true;

  /**
   * The count of resource files with the same hash. The key is the hash of the optimized resource file.
   *
   * @var int[string]
   */
  private $myHashCount;

  /**
   * The path to the parent resource dir (relative to the build dir).
   *
   * @var string
   */
  private $myParentResourceDir;

  /**
   * The names of the resource files.
   *
   * @var array
   */
  private $myResourceFileNames;

  /**
   * Array with information about file resources such as 'hash', 'content' etc.
   *
   * @var array
   */
  private $myResourceFilesInfo;

  /**
   * The ID of the fileset with resource files.
   *
   * @var string
   */
  private $myResourcesFilesetId;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   */
  public function __construct()
  {
    $this->myResourceFilesInfo = [];
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
   * Enhance all elements in {@link $this->myResourceFilesInfo} with an ordinal to prevent hash collisions. (In most
   * cases this ordinal will be 0.)
   *
   * @todo if content is equal reuse ordinal.
   */
  protected function enhanceResourceFilesInfoWithOrdinal()
  {
    $this->myHashCount = [];

    foreach ($this->myResourceFilesInfo as $file_info)
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
   * Get info about each file in the fileset.
   */
  protected function getInfoResourceFiles()
  {
    $this->logVerbose('Get resource files info.');

    $resource_dir = $this->getProject()->getReference($this->myResourcesFilesetId)->getDir($this->getProject());

    foreach ($this->myResourceFileNames as $filename)
    {
      clearstatcache();

      $path      = $resource_dir.'/'.$filename;
      $full_path = realpath($path);

      $this->store(file_get_contents($full_path), $full_path);
    }

    $suc = ksort($this->getResourcesInfo());
    if ($suc===false) $this->logError("ksort failed.");
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Gets the path name relative to the parent resource directory of a resource file.
   *
   * @param $thePath string The full path name of resource file.
   *
   * @return string The path name relative to the parent resource directory.
   * @throws \BuildException
   */
  protected function getPathInResources($thePath)
  {
    if (strncmp($thePath, $this->myParentResourceDirFullPath, strlen($this->myParentResourceDirFullPath))!=0)
    {
      throw new \BuildException(sprintf("Resource file '%s' is not under resource dir '%s'.",
                                        $thePath,
                                        $this->myParentResourceDirFullPath));
    }

    return substr($thePath, strlen($this->myParentResourceDirFullPath));
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the resource info based on the full path of the resource
   *
   * @param $theFullPathName
   *
   * @return array
   * @throws BuildException
   */
  protected function getResourceInfo($theFullPathName)
  {
    foreach ($this->myResourceFilesInfo as $key => $val)
    {
      if ($val['full_path_name']===$theFullPathName)
      {
        return $this->myResourceFilesInfo[$key];
      }
    }
    $args   = func_get_args();
    $format = array_shift($args);
    throw new \BuildException(vsprintf($format, $args));
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Return the resource info
   *
   * @return array
   */
  protected function getResourcesInfo()
  {
    return $this->myResourceFilesInfo;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Print to console Error Message
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

    if ($this->myHaltOnError) throw new \BuildException(vsprintf($format, $args));
    else $this->log(vsprintf($format, $args), \Project::MSG_ERR);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Print to console Info Message
   */
  protected function logInfo()
  {
    $args   = func_get_args();
    $format = array_shift($args);

    foreach ($args as &$arg)
    {
      if (!is_scalar($arg)) $arg = var_export($arg, true);
    }

    $this->log(vsprintf($format, $args), \Project::MSG_INFO);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Print to console Verbose Message
   */
  protected function logVerbose()
  {
    $args   = func_get_args();
    $format = array_shift($args);

    foreach ($args as &$arg)
    {
      if (!is_scalar($arg)) $arg = var_export($arg, true);
    }

    $this->log(vsprintf($format, $args), \Project::MSG_VERBOSE);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Minimizes JavaScript or CSS code.
   *
   * @param string $theResource     The JavaScript or CSS code.
   * @param string $theFullPathName The full pathname of the JavaScript or CSS file.
   *
   * @return string The minimized JavaScript or CSS code.
   */
  abstract protected function minimizeResource($theResource, $theFullPathName);

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Get info about all source, resource files and directories.
   */
  protected function prepareProjectData()
  {
    $this->logVerbose('Get source and resource file names.');

    // Get file list form the project by fileset ID.
    $resources                 = $this->getProject()->getReference($this->myResourcesFilesetId);
    $this->myResourceFileNames = $resources->getDirectoryScanner($this->getProject())->getIncludedFiles();

    // Get full path name of resource dir.
    $this->myParentResourceDirFullPath = realpath($resources->getDir($this->getProject()).'/'.$this->myParentResourceDir);

    // Get full path name of resource dir.
    $this->myResourceDirFullPath = realpath($this->myParentResourceDirFullPath.'/'.$this->myResourceDir);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Minimize resource, create hash based on optimized content. Add resource info into array.
   *
   * @param string $theResource     The (actual content) of the resource.
   * @param string $theFullPathName The full pathname of the file where the resource is stored.
   *
   * @return array
   * @throws BuildException
   */
  protected function store($theResource, $theFullPathName)
  {
    $this->logInfo("Minimizing '%s'.", $theFullPathName);

    $extension   = pathinfo($theFullPathName)['extension'];
    $content_opt = $this->minimizeResource($theResource, $theFullPathName);

    // @todo Ignore *.main.js files.

    $file_info                = [];
    $file_info['hash']        = md5($content_opt);
    $file_info['content_raw'] = $theResource;
    $file_info['content_opt'] = $content_opt;
    $file_info['ordinal']     = isset($this->myHashCount[$file_info['hash']]) ? $this->myHashCount[$file_info['hash']]++ : $this->myHashCount[$file_info['hash']] = 0;

    if (isset($theFullPathName))
    {
      $file_info['full_path_name']                 = $theFullPathName;
      $file_info['path_name_in_sources']           = $this->getPathInResources($theFullPathName);
      $file_info['full_path_name_with_hash']       = $this->myResourceDirFullPath.'/'.
        $file_info['hash'].'.'.$file_info['ordinal'].'.'.$extension;
      $file_info['path_name_in_sources_with_hash'] = $this->getPathInResources($file_info['full_path_name_with_hash']);
    }

    // Save the combined code.
    $bytes = file_put_contents($file_info['full_path_name_with_hash'], $file_info['content_opt']);
    if ($bytes===false) $this->logError("Unable to write to file '%s'.", $file_info['full_path_name_with_hash']);

    $this->myResourceFilesInfo[] = $file_info;

    return $file_info;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
