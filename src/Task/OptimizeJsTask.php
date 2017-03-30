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
  private $combineCommand;

  /**
   * Methods for including JS files.
   *
   * @var array
   */
  private $methods = ['jsAdmSetPageSpecificMain',
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
  private $minifyCommand = '/usr/bin/uglifyjs - -c -m';

  /**
   * The path to require.js relative to the parent resource path.
   *
   * @var string
   */
  private $requireJsPath = 'js/require.js';

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
   * @param string $realPath
   *
   * @return string
   */
  private static function getMainJsFileName($realPath)
  {
    $parts = pathinfo($realPath);

    return $parts['dirname'].'/'.$parts['filename'].'.main.'.$parts['extension'];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute combineCommand.
   *
   * @param string $combineCommand The command to run r.js.
   */
  public function setCombineCommand($combineCommand)
  {
    $this->combineCommand = $combineCommand;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute combineCommand.
   *
   * @param string $minifyCommand The command to run r.js.
   */
  public function setMinifyCommand($minifyCommand)
  {
    $this->minifyCommand = $minifyCommand;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute requireJsPath.
   *
   * @param string $requireJsPath The command to run r.js.
   */
  public function setRequireJsPath($requireJsPath)
  {
    $this->requireJsPath = $requireJsPath;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Minimizes JavaScript code.
   *
   * @param string $resource     The JavaScript code.
   * @param string $fullPathName The full pathname of the JavaScript file.
   *
   * @return string The minimized JavaScript code.
   */
  protected function minimizeResource($resource, $fullPathName)
  {
    list($std_out, $std_err) = $this->runProcess($this->minifyCommand, $resource);

    if ($std_err) $this->logInfo($std_err);

    return $std_out;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * In PHP code replaces references to resource files (i.e. CSS or JS files) with references to the optimized versions
   * of the resource files.
   *
   * @param string $filename The filename with the PHP code.
   * @param string $phpCode  The PHP code.
   *
   * @return string The modified PHP code.
   */
  protected function processPhpSourceFile($filename, $phpCode)
  {
    // If true the PHP code includes CSS files.
    $includes = false;
    foreach ($this->methods as $method)
    {
      if (stripos($phpCode, $method)!==false)
      {
        $includes = true;
        break;
      }
    }

    if ($includes)
    {
      $phpCode = $this->processPhpSourceFileReplaceMethod($filename, $phpCode);
    }

    return $phpCode;
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
   * @param string $filename The filename with the PHP code.
   * @param string $phpCode  The PHP code.
   *
   * @return string The modified PHP code.
   */
  protected function processPhpSourceFileReplaceMethod($filename, $phpCode)
  {
    $classes       = $this->getClasses($phpCode);
    $current_class = '';

    $lines = explode("\n", $phpCode);
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
        foreach ($this->methods as $method)
        {
          if (preg_match("/(->|::)($method)(\\()/", $line))
          {
            $this->logError("Unexpected usage of method '%s' at %s:%d.", $method, $filename, $i + 1);
          }
        }
      }
    }

    return implode("\n", $lines);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Combines all JavaScript files required by a main JavaScript file.
   *
   * @param string $realPath The path to the main JavaScript file.
   *
   * @return array The combined code and parts.
   * @throws BuildException
   */
  private function combine($realPath)
  {
    $config = $this->extractConfigFromMainFile(self::getMainJsFileName($realPath));

    // Create temporary file with config.
    $tmp_name1 = tempnam('.', 'abc_');
    $handle    = fopen($tmp_name1, 'w');
    fwrite($handle, $config);
    fclose($handle);

    // Create temporary file for combined JavaScript code.
    $tmp_name2 = tempnam($this->resourceDirFullPath, 'abc_');

    // Run r.js.
    $command = $this->combineCommand;
    $command .= ' -o '.escapeshellarg($tmp_name1);
    $command .= ' baseUrl='.escapeshellarg($this->resourceDirFullPath);
    $command .= ' optimize=none';
    $command .= ' name='.escapeshellarg($this->getNamespaceFromResourceFilename($realPath));
    $command .= ' out='.escapeshellarg($tmp_name2);

    $this->logVerbose('Execute: %s', $command);
    exec($command, $output, $ret);
    if ($ret!=0) $this->logError("Error executing '%s'.", $this->combineCommand);

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
    $path       = $this->parentResourceDirFullPath.'/'.$this->requireJsPath;
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
   * @param string $fullPath The path to the JavaScript file
   *
   * @return string
   * @throws BuildException
   */
  private function combineAndMinimize($fullPath)
  {
    $real_path = realpath($fullPath);

    $combine_info = $this->combine($real_path);
    $files_info   = $this->getMainWithHashedPaths($real_path);
    $js_raw       = $combine_info['code'];
    $js_raw .= $files_info;

    $file_info = $this->store($js_raw, $real_path, $combine_info['parts'], 'full_path_name');

    return $file_info['path_name_in_sources_with_hash'];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @param string $filename
   */
  private function extractConfigFromMainFile($filename)
  {
    $main = file_get_contents($filename);
    if ($main===false) $this->logError("Unable to read file '%s'.", $filename);

    preg_match('/^(.*requirejs.config)(.*}\))(.*)$/sm', $main, $matches);
    if (!isset($matches[2])) $this->logError("Unable to fine 'requirejs.config' in file '%s'.", $filename);

    return $matches[2];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Reads the main.js file and returns baseUrl and paths.
   *
   * @param $mainJsFile
   *
   * @return array
   */
  private function extractPaths($mainJsFile)
  {
    $extract_script = __DIR__.'/../../lib/extract_config.js';
    $command        = 'node';
    $command .= ' '.escapeshellarg($extract_script);
    $command .= ' '.escapeshellarg($mainJsFile);
    $output = shell_exec($command);
    if ($output===null) $this->logError("Command '%s' return failed.", $command);
    $config = json_decode($output, true);

    return [$config['baseUrl'], $config['paths']];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the full path of a ADM JavaScript file based on a PHP class name.
   *
   * @param string $className The PHP class name.
   *
   * @return string
   */
  private function getFullPathFromClassName($className)
  {
    $file_name = str_replace('\\', '/', $className).$this->extension;
    $full_path = $this->resourceDirFullPath.'/'.$file_name;

    return $full_path;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the full path of a ADM JavaScript file based on a ADM namespace.
   *
   * @param string $namespace The ADM namespace.
   *
   * @return string
   */
  private function getFullPathFromNamespace($namespace)
  {
    $file_name = $namespace.$this->extension;
    $full_path = $this->resourceDirFullPath.'/'.$file_name;

    return $full_path;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Rewrites paths in requirejs.config. Adds path names from namespaces and aliases to filenames with hashes.
   *
   * @param string $realPath The filename of the main.js file.
   *
   * @return string
   * @throws BuildException
   */
  private function getMainWithHashedPaths($realPath)
  {
    $main_js_file = self::getMainJsFileName($realPath);
    // Read the main file.
    $js = file_get_contents($main_js_file);
    if ($js===false) $this->logError("Unable to read file '%s'.", $realPath);

    // Extract paths from main.
    preg_match('/^(.*paths:[^{]*)({[^}]*})(.*)$/sm', $js, $matches);
    if (!isset($matches[2])) $this->logError("Unable to find paths in '%s'.", $realPath);

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
   * @param string $className The PHP class name.
   *
   * @return string
   */
  private function getNamespaceFromClassName($className)
  {
    return str_replace('\\', '/', $className);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the namespace based on the name of a JavaScript file.
   *
   * @param string $resourceFilename The name of the JavaScript file.
   *
   * @return string
   * @throws BuildException
   */
  private function getNamespaceFromResourceFilename($resourceFilename)
  {
    $name = $this->getPathInResources($resourceFilename);

    // Remove resource dir from name.
    $len = strlen(trim($this->resourceDir, '/'));
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
   * @param string[] $matches         The matches as returned by preg_match.
   * @param string   $optimizedMethod The appropriate optimized method.
   * @param string   $namespace       The current class name of the PHP code.
   * @param string   $fullPath        The full path to the JS source.
   *
   * @return string
   * @throws BuildException
   */
  private function processPhpSourceFileReplaceMethodHelper($matches,
                                                           $optimizedMethod,
                                                           $namespace = null,
                                                           $fullPath = null)
  {
    $matches[3] = $optimizedMethod;
    if (isset($fullPath))
    {
      $matches[5] = "'".$this->combineAndMinimize($fullPath)."'";
      $full_path  = $fullPath;
    }
    elseif (isset($namespace))
    {
      $matches[5] = "'".$namespace."'";
      $full_path  = $this->getFullPathFromNamespace($namespace);
    }
    else
    {
      $full_path = $this->getFullPathFromNamespace($matches[5]);
    }

    if (!file_exists($full_path))
    {
      $this->logError("File '%s' not found.", $full_path);
    }

    array_shift($matches);

    return implode('', $matches);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Removes the .js (if any) extension form a filename.
   *
   * @param $filename string Filename
   *
   * @return string Filename without .js extension.
   */
  private function removeJsExtension($filename)
  {
    $extension = substr($filename, -strlen($this->extension));
    if ($extension==$this->extension)
    {
      return substr($filename, 0, -strlen($this->extension));
    }

    return $filename;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
