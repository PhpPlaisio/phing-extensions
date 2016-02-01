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
   * The size of buffers for reading stdout and stderr of sub-processes.
   *
   * @var int
   */
  const BUFFER_SIZE = 8000;

  /**
   * The command to run r.js.
   *
   * @var string
   */
  private $myCombineCommand;

  /**
   * Methods for including JS files.
   *
   * @var array
   */
  private $myMethods = ['jsAdmSetPageSpecificMain',
                        'jsAdmOptimizedSetPageSpecificMain',
                        'jsAdmPageSpecificFunctionCall',
                        'jsAdmFunctionCall',
                        'jsAdmOptimizedFunctionCall',
                        'jsAdmStaticClassSpecificFunctionCall',
                        'jsAdmStaticFunctionCall',
                        'jsAdmStaticOptimizedFunctionCall'];

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
   * @param string $theFilename The filename with the PHP code.
   * @param string $thePhpCode  The PHP code.
   *
   * @return string The modified PHP code.
   */
  protected function processPhpSourceFile($theFilename, $thePhpCode)
  {
    // If true the PHP code includes CSS files.
    $includes = false;
    foreach ($this->myMethods as $method)
    {
      if (stripos($thePhpCode, $method)!==false)
      {
        $includes = true;
        break;
      }
    }

    if ($includes)
    {
      $thePhpCode = $this->processPhpSourceFileReplaceMethod($theFilename, $thePhpCode);
    }

    return $thePhpCode;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Replaces calls to methods:
   * <ul>
   * <li>{@link \SetBased\Abc\Page\Page::jsAdmSetPageSpecificMain)
   * <li>{@link \SetBased\Abc\Page\Page::jsAdmPageSpecificFunctionCall)
   * <li>{@link \SetBased\Abc\Page\Page::jsAdmFunctionCall)
   * <li>{@link \SetBased\Abc\Page\Page::jsAdmStaticClassSpecificFunctionCall)
   * <li>{@link \SetBased\Abc\Page\Page::jsAdmStaticFunctionCall)
   * </ul>
   * with the appropriate optimized method.
   *
   * @param string $theFilename The filename with the PHP code.
   * @param string $thePhpCode  The PHP code.
   *
   * @return string The modified PHP code.
   */
  protected function processPhpSourceFileReplaceMethod($theFilename, $thePhpCode)
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

      // Don't process the class that defines the jsAdm* methods.
      if ($current_class=='SetBased\\Abc\\Page\\Page') continue;

      // Replace calls to jsAdmSetPageSpecificMain with jsAdmOptimizedSetPageSpecificMain.
      if (preg_match('/^(\s*)(\$this->)(jsAdmSetPageSpecificMain)(\(\s*)(__CLASS__)(\s*\)\s*;)(.*)$/',
                     $line,
                     $matches))
      {
        $lines[$i] = $this->processPhpSourceFileReplaceMethodHelper($matches,
                                                                    'jsAdmOptimizedSetPageSpecificMain',
                                                                    null,
                                                                    $this->getFullPathFromClassName($current_class));
      }

      // Replace calls to jsAdmPageSpecificFunctionCall with jsAdmOptimizedFunctionCall.
      elseif (preg_match('/^(\s*)(\$this->)(jsAdmPageSpecificFunctionCall)(\(\s*)(__CLASS__)(.*)$/',
                         $line,
                         $matches))
      {
        $lines[$i] = $this->processPhpSourceFileReplaceMethodHelper($matches,
                                                                    'jsAdmOptimizedFunctionCall',
                                                                    $this->getNamespaceFromClassName($current_class));
      }

      // Replace calls to jsAdmFunctionCall with jsAdmOptimizedFunctionCall.
      elseif (preg_match('/^(\s*)(\$this->)(jsAdmFunctionCall)(\(\s*[\'"])([a-zA-Z0-9_\-\.\/]+)([\'"].*)$/',
                         $line,
                         $matches))
      {
        $lines[$i] = $this->processPhpSourceFileReplaceMethodHelper($matches, 'jsAdmOptimizedFunctionCall');
      }

      // Replace calls to Page::jsAdmStaticClassSpecificFunctionCall with Page::jsAdmStaticOptimizedFunctionCall.
      elseif (preg_match('/^(\s*)(Page::)(jsAdmStaticClassSpecificFunctionCall)(\(\s*)(__CLASS__)(.*)$/',
                         $line,
                         $matches))
      {
        $lines[$i] = $this->processPhpSourceFileReplaceMethodHelper($matches,
                                                                    'jsAdmStaticOptimizedFunctionCall',
                                                                    $this->getNamespaceFromClassName($current_class));
      }

      // Replace calls to Page::jsAdmStaticFunctionCall with Page::jsAdmStaticOptimizedFunctionCall.
      elseif (preg_match('/^(\s*)(Page::)(jsAdmStaticFunctionCall)(\(\s*[\'"])([a-zA-Z0-9_\-\.\/]+)([\'"].*)$/',
                         $line,
                         $matches))
      {
        $lines[$i] = $this->processPhpSourceFileReplaceMethodHelper($matches, 'jsAdmStaticOptimizedFunctionCall');
      }

      // Test for invalid usages of methods for calling/including JS.
      else
      {
        foreach ($this->myMethods as $method)
        {
          if (preg_match("/(->|::)($method)(\\()/", $line))
          {
            $this->logError("Unexpected usage of method '%s' at %s:%d.", $method, $theFilename, $i + 1);
          }
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
    $std_in      = $theInput;
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
            $data = fread($read, self::BUFFER_SIZE);
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
            $data = fread($read, self::BUFFER_SIZE);
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
        $bytes = fwrite($writes[0], $std_in);
        if ($bytes===false) $this->logError("Unable to write to standard input of command '%s'.", $theCommand);
        if ($bytes==0)
        {
          fclose($writes[0]);
          unset($write_pipes[0]);
        }
        else
        {
          $std_in = substr($std_in, $bytes);
        }
      }
    }

    // Close the process and it return value.
    $ret = proc_close($process);
    if ($ret!=0)
    {
      if ($std_err!=='') $this->logInfo($std_err);
      else               $this->logInfo($std_out);
      $this->logError("Error executing '%s'.", $theCommand);
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
   * @return array The combined code and parts.
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
    if ($ret!=0) $this->logError("Error executing '%s'.", $this->myCombineCommand);

    foreach ($output as $line)
    {
      $this->logInfo($line);
    }
    if ($ret!=0) $this->logError("RequireJS optimizer failed.");

    // Get all files of the combined code.
    $parts   = [];
    $trigger = array_search('----------------', $output);
    foreach ($output as $index => $file)
    {
      if ($index>$trigger && !empty($file))
      {
        $parts[] = $file;
      }
    }

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

    return ['code' => $code, 'parts' => $parts];
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

    $combine_info = $this->combine($real_path);
    $files_info   = $this->getMainWithHashedPaths($real_path);
    $js_raw       = $combine_info['code'];
    $js_raw .= $files_info;

    $file_info = $this->store($js_raw, $real_path, $combine_info['parts'], 'full_path_name');

    return $file_info['path_name_in_sources_with_hash'];
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

    // Lookup table as paths in requirejs.config, however, keys and values are flipped.
    $paths = [];
    // Replace aliases to paths with aliases to paths with hashes (i.e. paths to minimized files).
    list($base_url, $aliases) = $this->extractPaths($main_js_file);
    if (isset($base_url) && isset($paths))
    {
      foreach ($aliases as $alias => $path)
      {
        $path_with_hash = $this->getPathInResourcesWithHash($base_url, $path);
        if (isset($path_with_hash))
        {
          $paths[$this->removeJsExtension($path_with_hash)] = $alias;
        }
      }
    }

    // Add paths from modules that conform to ADM naming convention to paths with hashes (i.e. path to minimized files).
    foreach ($this->getResourcesInfo() as $info)
    {
      // @todo Skip *.main.js files.

      // Test JS file is not already in paths, e.g. 'jquery': 'jquery/jquery'.
      if (!isset($paths[$this->removeJsExtension($info['path_name_in_sources_with_hash'])]))
      {
        if (isset($info['path_name_in_sources']))
        {
          $module                 = $this->getNamespaceFromResourceFilename($info['full_path_name']);
          $path_with_hash         = $this->getNamespaceFromResourceFilename($info['full_path_name_with_hash']);
          $paths[$path_with_hash] = $module;
        }
      }
    }

    // Convert the paths to proper JS code.
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
  /**
   * Helper function for {@link processPhpSourceFileReplaceMethodHelper}.
   *
   * @param string[] $theMatches         The matches as returned by preg_match.
   * @param string   $theOptimizedMethod The appropriate optimized method.
   * @param string   $theNameSpace       The current class name of the PHP code.
   * @param string   $theFullPath        The full path to the JS source.
   *
   * @return string
   * @throws BuildException
   */
  private function processPhpSourceFileReplaceMethodHelper($theMatches,
                                                           $theOptimizedMethod,
                                                           $theNameSpace = null,
                                                           $theFullPath = null)
  {
    $theMatches[3] = $theOptimizedMethod;
    if (isset($theFullPath))
    {
      $theMatches[5] = "'".$this->combineAndMinimize($theFullPath)."'";
      $full_path     = $theFullPath;
    }
    elseif (isset($theNameSpace))
    {
      $theMatches[5] = "'".$theNameSpace."'";
      $full_path     = $this->getFullPathFromNamespace($theNameSpace);
    }
    else
    {
      $full_path = $this->getFullPathFromNamespace($theMatches[5]);
    }

    if (!file_exists($full_path))
    {
      $this->logError("File '%s' not found.", $full_path);
    }

    array_shift($theMatches);

    return implode('', $theMatches);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Removes the .js (if any) extension form a filename.
   *
   * @param $theFileName string Filename
   *
   * @return string Filename without .js extension.
   */
  private function removeJsExtension($theFileName)
  {
    $extension = substr($theFileName, -strlen($this->myExtension));
    if ($extension==$this->myExtension)
    {
      return substr($theFileName, 0, -strlen($this->myExtension));
    }

    return $theFileName;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
