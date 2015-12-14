<?php
//----------------------------------------------------------------------------------------------------------------------
require_once 'OptimizeResourceTask.php';

//----------------------------------------------------------------------------------------------------------------------
/**
 * Class for optimizing and combining JS files.
 */
class OptimizeJsTask extends \OptimizeResourceTask
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The command to run r.js.
   *
   * @var string
   */
  private $myCombineCommand;

  /**
   * The command to minify JS.
   *
   * @var string
   */
  private $myMinifyCommand;

  /**
   * The path to require.js relative to the parent resource path.
   *
   * @var string
   */
  private $myRequireJsPath = 'js/require.js';

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * OptimizeJsTask constructor.
   */
  public function __construct()
  {
    parent::__construct('.js');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the 'main' file of a page specific main JavaScript file.
   *
   * @param string $theRealPath
   *
   * @return string
   */
  private static function getMainJsFileName($theRealPath)
  {
    $parts = pathinfo($theRealPath);

    return $parts['dirname'].'/'.$parts['filename'].'.main.'.$parts['extension'];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute combineCommand.
   *
   * @param string $theCombineCommand The command to run r.js.
   */
  public function setCombineCommand($theCombineCommand)
  {
    $this->myCombineCommand = $theCombineCommand;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute combineCommand.
   *
   * @param string $theMinifyCommand The command to run r.js.
   */
  public function setMinifyCommand($theMinifyCommand)
  {
    $this->myMinifyCommand = $theMinifyCommand;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Minimizes JavaScript code.
   *
   * @param string $theResource     The JavaScript code.
   * @param string $theFullPathName The full pathname of the JavaScript file.
   *
   * @return string The minimized JavaScript code.
   */
  protected function minimizeResource($theResource, $theFullPathName)
  {
    list($std_out, $std_err) = $this->runProcess($this->myMinifyCommand, $theResource);

    if ($std_err) $this->logInfo($std_err);

    return $std_out;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * In PHP code replaces references to resource files (i.e. CSS or JS files) with references to the optimized versions
   * of the resource files.
   *
   * @param string $thePhpCode The PHP code.
   *
   * @return string The modified PHP code.
   */
  protected function processPhpSourceFile($thePhpCode)
  {
    // Methods for including JS files.
    $methods = ['jsAdmPageSpecificFunctionCall',
                'jsAdmFunctionCall',
                'jsAdmOptimizedFunctionCall',
                'jsAdmSetPageSpecificMain',
                'jsAdmOptimizedSetPageSpecificMain'];

    // If true the PHP code includes CSS files.
    $includes = false;
    foreach ($methods as $method)
    {
      if (stripos($thePhpCode, $method)!==false)
      {
        $includes = true;
        break;
      }
    }

    if ($includes)
    {
      $thePhpCode = $this->processPhpSourceFileReplaceMethod($thePhpCode);
    }

    return $thePhpCode;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Replaces calls to method {@link \SetBased\Abc\Page\Page::jsAdmSetPageSpecificMain) with calls to
   * {@link \SetBased\Abc\Page\Page::jsAdmOptimizedSetPageSpecificMain}.
   *
   * @param string $thePhpCode The PHP code.
   *
   * @return string The modified PHP code.
   */
  protected function processPhpSourceFileReplaceMethod($thePhpCode)
  {
    $classes       = $this->getClasses($thePhpCode);
    $current_class = '';

    $lines = explode("\n", $thePhpCode);
    foreach ($lines as $i => $line)
    {
      if (isset($classes[$i + 1]))
      {
        if (isset($classes[$i + 1]['namespace']))
        {
          $current_class = $classes[$i + 1]['namespace'].'\\'.$classes[$i + 1]['class'];
        }
      }

      // Replace calls to jsAdmSetPageSpecificMain with jsAdmOptimizedSetPageSpecificMain.
      if (preg_match('/^(\s*)([\$:a-zA-Z0-9_]+->)(jsAdmSetPageSpecificMain)(\(\s*)(__CLASS__)(\s*\)\s*;)(.*)$/',
                     $line,
                     $matches))
      {
        $full_path = $this->getFullPathFromClassName($current_class);
        if (!file_exists($full_path))
        {
          $this->logError("File '%s' not found.", $full_path);
        }
        else
        {
          $matches[3] = 'jsAdmOptimizedSetPageSpecificMain';
          $matches[5] = "'".$this->combineAndMinimize($full_path)."'";

          array_shift($matches);
          $lines[$i] = implode('', $matches);
        }
      }

      // Replace calls to jsAdmPageSpecificFunctionCall with jsAdmOptimizedFunctionCall.
      if (preg_match('/^(\s*)([\$:a-zA-Z0-9_]+->)(jsAdmPageSpecificFunctionCall)(\(\s*)(__CLASS__)(.*)$/',
                     $line,
                     $matches))
      {
        $full_path = $this->getFullPathFromClassName($current_class);
        if (!file_exists($full_path))
        {
          $this->logError("File '%s' not found.", $full_path);
        }
        else
        {
          $matches[3] = 'jsAdmOptimizedFunctionCall';
          $matches[5] = "'".$this->getNamespaceFromClassName($current_class)."'";

          array_shift($matches);
          $lines[$i] = implode('', $matches);
        }
      }

      // Replace calls to jsAdmFunctionCall with jsAdmOptimizedFunctionCall.
      if (preg_match('/^(\s*)([\$:a-zA-Z0-9_]+->)(jsAdmFunctionCall)(\(\s*[\'"])([a-zA-Z0-9_\-\.\/]+)([\'"].*)$/',
                     $line,
                     $matches))
      {
        $full_path = $this->getFullPathFromNamespace($matches[5]);
        if (!file_exists($full_path))
        {
          $this->logError("File '%s' not found.", $full_path);
        }
        else
        {
          $matches[3] = 'jsAdmOptimizedFunctionCall';

          array_shift($matches);
          $lines[$i] = implode('', $matches);
        }
      }
    }

    return implode("\n", $lines);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Executes a command and writes data to the standard input and reads data from the standard output and error of the
   * process.
   *
   * @param string $theCommand The command to run.
   * @param string $theInput   The data to send to the process.
   *
   * @return string[] An array with two elements: the standard output and the standard error.
   * @throws BuildException
   */
  protected function runProcess($theCommand, $theInput)
  {
    $descriptor_spec = [0 => ["pipe", "r"],
                        1 => ["pipe", "w"],
                        2 => ["pipe", "w"]];

    $process = proc_open($theCommand, $descriptor_spec, $pipes);
    if ($process===false) $this->logError("Unable to span process '%s'.", $theCommand);

    $write_pipes = [$pipes[0]];
    $read_pipes  = [$pipes[1], $pipes[2]];
    $std_out     = '';
    $std_err     = '';
    while (true)
    {
      $reads  = $read_pipes;
      $writes = $write_pipes;
      $except = null;

      if (!$reads && !$writes) break;

      stream_select($reads, $writes, $except, 1);
      if ($reads)
      {
        foreach ($reads as $read)
        {
          if ($read==$pipes[1])
          {
            $data = fread($read, 8000);
            if ($data===false) $this->logError("Unable to read standard output from command '%s'.", $theCommand);
            if ($data==='')
            {
              fclose($pipes[1]);
              unset($read_pipes[0]);
            }
            else
            {
              $std_out .= $data;
            }
          }
          if ($read==$pipes[2])
          {
            $data = fread($read, 8000);
            if ($data===false) $this->logError("Unable to read standard error from command '%s'.", $theCommand);
            if ($data==='')
            {
              fclose($pipes[2]);
              unset($read_pipes[1]);
            }
            else
            {
              $std_err .= $data;
            }
          }
        }
      }

      if (isset($writes[0]))
      {
        $bytes = fwrite($writes[0], $theInput);
        if ($bytes===false) $this->logError("Unable to write to standard input of command '%s'.", $theCommand);
        if ($bytes==0)
        {
          fclose($writes[0]);
          unset($write_pipes[0]);
        }
        else
        {
          $theInput = substr($theInput, $bytes);
        }
      }
    }

    return [$std_out, $std_err];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute requireJsPath.
   *
   * @param string $theRequireJsPath The command to run r.js.
   */
  protected function setRequireJsPath($theRequireJsPath)
  {
    $this->myRequireJsPath = $theRequireJsPath;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Combines all JavaScript files required by a main JavaScript file.
   *
   * @param string $theRealPath The path to the main JavaScript file.
   *
   * @return string The combined code.
   * @throws BuildException
   */
  private function combine($theRealPath)
  {
    $config = $this->extractConfigFromMainFile(self::getMainJsFileName($theRealPath));

    // Create temporary file with config.
    $tmp_name1 = tempnam('.', 'abc_');
    $handle    = fopen($tmp_name1, 'w');
    fwrite($handle, $config);
    fclose($handle);

    // Create temporary file for combined JavaScript code.
    $tmp_name2 = tempnam($this->myResourceDirFullPath, 'abc_');

    // Run r.js.
    $command = $this->myCombineCommand;
    $command .= ' -o '.escapeshellarg($tmp_name1);
    $command .= ' baseUrl='.escapeshellarg($this->myResourceDirFullPath);
    $command .= ' optimize=none';
    $command .= ' name='.escapeshellarg($this->getNamespaceFromResourceFilename($theRealPath));
    $command .= ' out='.escapeshellarg($tmp_name2);

    $this->logVerbose("Execute: $command");
    exec($command, $output, $ret);

    foreach ($output as $line)
    {
      $this->logInfo($line);
    }
    if ($ret!=0) $this->logError("RequireJS optimizer failed.");

    // @todo Get all files of the combined code.

    // Get the combined the JavaScript code.
    $code = file_get_contents($tmp_name2);
    if ($code===false) $this->logError("Unable to read file '%s'.", $tmp_name2);

    // Get require.js
    $path       = $this->myParentResourceDirFullPath.'/'.$this->myRequireJsPath;
    $require_js = file_get_contents($path);
    if ($code===false) $this->logError("Unable to read file '%s'.", $path);

    // Combine require.js and all required includes.
    $code = $require_js.$code;

    // Remove temporary files.
    unlink($tmp_name2);
    unlink($tmp_name1);

    return $code;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Creates file and minimizes in which all required JavaScript files of a page specific RequireJs file are combined,
   * see {@link \SetBased\Abc\Page\Page::jsAdmSetPageSpecificMain}.
   *
   * @param string $theFullPath The path to the JavaScript file
   *
   * @return string
   * @throws BuildException
   */
  private function combineAndMinimize($theFullPath)
  {
    $real_path = realpath($theFullPath);

    $js_raw = $this->combine($real_path);
    $js_raw .= $this->getMainWithHashedPaths($real_path);

    $file_info = $this->store($js_raw, $real_path);

    return $file_info['path_name_in_sources_with_hash'];
    // @todo Set mtime of the combined code.
    // @todo Set file permissions.
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @param string $theFilename
   */
  private function extractConfigFromMainFile($theFilename)
  {
    $main = file_get_contents($theFilename);
    if ($main===false) $this->logError("Unable to read file '%s'.", $theFilename);

    preg_match('/^(.*requirejs.config)(.*}\))(.*)$/sm', $main, $matches);
    if (!isset($matches[2])) $this->logError("Unable to fine 'requirejs.config' in file '%s'.", $theFilename);

    return $matches[2];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Reads the main.js file and returns baseUrl and paths.
   *
   * @param $theMainJsFile
   *
   * @return array
   */
  private function extractPaths($theMainJsFile)
  {
    $extract_script = __DIR__.'/../../lib/extract_config.js';
    $command        = 'node';
    $command .= ' '.escapeshellarg($extract_script);
    $command .= ' '.escapeshellarg($theMainJsFile);
    $output = shell_exec($command);
    if ($output===null) $this->logError("Command '%s' return failed.", $command);
    $config = json_decode($output, true);

    return [$config['baseUrl'], $config['paths']];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the full path of a ADM JavaScript file based on a PHP class name.
   *
   * @param string $theClassName The PHP class name.
   *
   * @return string
   */
  private function getFullPathFromClassName($theClassName)
  {
    $file_name = str_replace('\\', '/', $theClassName).$this->myExtension;
    $full_path = $this->myResourceDirFullPath.'/'.$file_name;

    return $full_path;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the full path of a ADM JavaScript file based on a ADM namespace.
   *
   * @param string $theNamespace The ADM namespace.
   *
   * @return string
   */
  private function getFullPathFromNamespace($theNamespace)
  {
    $file_name = $theNamespace.$this->myExtension;
    $full_path = $this->myResourceDirFullPath.'/'.$file_name;

    return $full_path;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Function for remove file extension
   *
   * @param $theFileName string File name
   *
   * @return string File name without extension
   */
  private function removeExtension($theFileName)
  {
    return substr($theFileName,0,-strlen($this->myExtension));
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Rewrites paths in requirejs.config. Adds path names from namespaces and aliases to filenames with hashes.
   *
   * @param string $theRealPath The filename of the main.js file.
   *
   * @return string
   * @throws BuildException
   */
  private function getMainWithHashedPaths($theRealPath)
  {
    $main_js_file = self::getMainJsFileName($theRealPath);
    // Read the main file.
    $js = file_get_contents($main_js_file);
    if ($js===false) $this->logError("Unable to read file '%s'.", $theRealPath);

    // Extract paths from main.
    preg_match('/^(.*paths:[^{]*)({[^}]*})(.*)$/sm', $js, $matches);
    if (!isset($matches[2])) $this->logError("Unable to find paths in '%s'.", $theRealPath);

    // @todo Remove from paths files already combined.

    $paths = [];

    // Replace aliases to paths with aliases to paths with hashes (i.e. path to minimized files).
    list($base_url, $aliases) = $this->extractPaths($main_js_file);
    if (isset($base_url) && isset($paths))
    {
      foreach ($aliases as $alias => $path)
      {
        $path_with_hash = $this->getPathInResourcesWithHash($base_url, $path);
        if (isset($path_with_hash))
        {
          $extension = substr($path_with_hash,-strlen($this->myExtension),strlen($path_with_hash));
          if($extension===$this->myExtension)
            $path_with_hash = $this->removeExtension($path_with_hash);
          $paths[$path_with_hash] = $alias;
        }
      }
    }

    // Add paths from namespaces to to paths with hashes (i.e. path to minimized files).
    foreach ($this->getResourcesInfo() as $info)
    {
      // @todo Skip *.main.js files.

      if (!isset($paths[$this->removeExtension($info['path_name_in_sources_with_hash'])]))
      {
        if (isset($info['path_name_in_sources']))
        {
          $namespace              = $this->getNamespaceFromResourceFilename($info['full_path_name']);
          $path_with_hash         = $this->getNamespaceFromResourceFilename($info['full_path_name_with_hash']);
          $paths[$path_with_hash] = $namespace;
        }
      }
    }

    $matches[2] = json_encode(array_flip($paths), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    array_shift($matches);
    $js = implode('', $matches);

    return $js;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the namespace in JS file based on PHP class name.
   *
   * @param string $theClassName The PHP class name.
   *
   * @return string
   */
  private function getNamespaceFromClassName($theClassName)
  {
    return str_replace('\\', '/', $theClassName);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the namespace based on the name of a JavaScript file.
   *
   * @param string $theResourceFilename The name of the JavaScript file.
   *
   * @return string
   * @throws BuildException
   */
  private function getNamespaceFromResourceFilename($theResourceFilename)
  {
    $name = $this->getPathInResources($theResourceFilename);

    // Remove resource dir from name.
    $len = strlen(trim($this->myResourceDir, '/'));
    if ($len>0)
    {
      $name = substr($name, $len + 2);
    }

    // Remove extension.
    $parts = pathinfo($name);
    $name  = substr($name, 0, -(strlen($parts['extension']) + 1));

    return $name;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
