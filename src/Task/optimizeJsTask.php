<?php
//----------------------------------------------------------------------------------------------------------------------
require_once 'optimizeResourceTask.php';

//----------------------------------------------------------------------------------------------------------------------
/**
 * Class for optimizing and combining JS files.
 */
class optimizeJsTask extends optimizeResourceTask
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The command to run r.js.
   *
   * @var string
   */
  private $myCombineCommand;

  /**
   * The command to run uglifyjs.
   *
   * @var string
   */
  private $myMinifyCommand;

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
   * Optimizes/minimizes all resource files (in the resource file set).
   */
  protected function optimizeResourceFiles()
  {
    foreach ($this->myResourceFilesInfo as &$file_info)
    {
      $this->logInfo("Minimizing '%s'.", $file_info['full_path_name']);

      $js_raw = file_get_contents($file_info['full_path_name']);
      if ($js_raw===false) $this->logError("Unable to read file '%s'.", $file_info['full_path_name']);
      $js_opt = $this->minimizeJs($js_raw);

      // @todo Ignore *.main.js files.

      $file_info['hash']        = md5($js_opt);
      $file_info['content_raw'] = $js_raw;
      $file_info['content_opt'] = $js_opt;
    }
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
    $methods = ['callPageSpecificJsFunction',
                'callJsFunction',
                'setPageSpecificRequireJsMain',
                'setOptimizedPageSpecificRequireJsMain'];

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
   * Replaces calls to method {@link \SetBased\Abc\Page\Page::setPageSpecificRequireJsMain) with calls to
   * {@link \SetBased\Abc\Page\Page::setOptimizedPageSpecificRequireJsMain}.
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

      // Replace calls to setPageSpecificRequireJsMain with setOptimizedPageSpecificRequireJsMain.
      if (preg_match('/^(.*)(\$this->)(setPageSpecificRequireJsMain)(\(\s*)(__CLASS__)(\s*\)\s*;)(.*)$/',
                     $line,
                     $matches))
      {
        $file_name = str_replace('\\', '/', $current_class).'.js';
        $full_path = $this->myResourceDirFullPath.'/'.$file_name;
        if (!file_exists($full_path))
        {
          $this->logError("File '%s' not found.", $full_path);
        }
        else
        {
          $real_path  = realpath($full_path);
          $matches[3] = 'setOptimizedPageSpecificRequireJsMain';
          $matches[5] = "'".$this->combineAndMinimize($real_path)."'";

          array_shift($matches);
          $lines[$i] = implode('', $matches);
        }
      }

      // Replace calls to callPageSpecificJsFunction with callJsFunction.
      if (preg_match('/^(.*)(\$this->)(callPageSpecificJsFunction)(\(\s*)(__CLASS__)(.*)$/',
                     $line,
                     $matches))
      {
        $file_name = str_replace('\\', '/', $current_class).'.js';
        $full_path = $this->myResourceDirFullPath.'/'.$file_name;
        if (!file_exists($full_path))
        {
          $this->logError("File '%s' not found.", $full_path);
        }
        else
        {
          $matches[3] = 'callOptimizedJsFunction';
          $matches[5] = "'".$this->getNamespaceFromResourceFilename(realpath($full_path))."'";

          array_shift($matches);
          $lines[$i] = implode('', $matches);
        }
      }

      // Replace calls to callPageSpecificJsFunction with callJsFunction.
      if (preg_match('/^(.*)(\$this->)(callJsFunction)(\(\s*[\'"])([a-zA-Z0-9_\-\.\/]+)([\'"].*)$/',
                     $line,
                     $matches))
      {
        $file_name = $matches[5];
        $full_path = $this->myResourceDirFullPath.'/'.$file_name.'.js';
        if (!file_exists($full_path))
        {
          $this->logError("File '%s' not found.", $full_path);
        }
        else
        {
          $real_path  = realpath($full_path);
          $matches[3] = 'callOptimizedJsFunction';
          $matches[5] = $this->myResourceFilesInfo[$real_path]['path_name_in_sources_with_hash'];

          array_shift($matches);
          $lines[$i] = implode('', $matches);
        }
      }
    }

    return implode("\n", $lines);
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

    // Remove temporary files.
    unlink($tmp_name2);
    unlink($tmp_name1);

    return $code;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Creates file and minimizes in which all required JavaScript files of a page specific RequireJs file are combined,
   * see {@link \SetBased\Abc\Page\Page::setPageSpecificRequireJsMain}.
   *
   * @param string $theRealPath The path to the JavaScript file
   *
   * @return string
   * @throws BuildException
   */
  private function combineAndMinimize($theRealPath)
  {
    $js_raw = $this->combine($theRealPath);
    $js_raw .= $this->getMainWithHashedPaths($theRealPath);

    $js_opt = $this->minimizeJs($js_raw);

    $file_info2['hash']        = md5($js_opt);
    $file_info2['content_raw'] = $js_raw;
    $file_info2['content_opt'] = $js_opt;

    // Compute hash of the combined code.
    $file_info2['hash'] = md5($file_info2['content_opt']);

    // Compute the ordinal of the hash code.
    if (!isset($this->myHashCount[$file_info2['hash']]))
    {
      $this->myHashCount[$file_info2['hash']] = 0;
    }
    $file_info2['ordinal'] = $this->myHashCount[$file_info2['hash']];
    $this->myHashCount[$file_info2['hash']]++;

    // Set the full path with hash of the combined file.
    $file_info2['full_path_name_with_hash']       = $this->myResourceDirFullPath.'/'.
      $file_info2['hash'].'.'.$file_info2['ordinal'].'.js';
    $file_info2['path_name_in_sources_with_hash'] = $this->getPathInResources($file_info2['full_path_name_with_hash']);

    // Save the combined code.
    $bytes = file_put_contents($file_info2['full_path_name_with_hash'], $file_info2['content_opt']);
    if ($bytes===false) $this->logError("Unable to write to file '%s'.", $file_info2['full_path_name_with_hash']);

    $this->myResourceFilesInfo[] = $file_info2;

    // @todo Set mtime of the combined code.
    // @todo Set file permissions.

    return $file_info2['path_name_in_sources_with_hash'];
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
   * Reads the main.js file and adds paths from namespaces to filenames with hashes.
   *
   * @param string $theRealPath The filename of the main file.
   *
   * @return string
   * @throws BuildException
   */
  private function getMainWithHashedPaths($theRealPath)
  {
    // Read the main file.
    $js = file_get_contents(self::getMainJsFileName($theRealPath));
    if ($js===false) $this->logError("Unable to read file '%s'.", $theRealPath);

    // Extract paths from main.
    preg_match('/^(.*paths:[^{]*{)([^}]*)(}.*)$/sm', $js, $matches);
    if (!isset($matches[2])) $this->logError("Unable to find paths in '%s'.", $theRealPath);

    // @todo Remove from paths files already combined.
    // @todo Replace existing paths with hashed.
    foreach ($this->myResourceFilesInfo as $info)
    {
      // @todo Skip files already combined.
      // @todo Skip *.main.js files.
      if (isset($info['path_name_in_sources']))
      {
        if ($matches[2]) $matches[2] .= ",\n";
        $matches[2] .= "'";
        $matches[2] .= $this->getNamespaceFromResourceFilename($info['full_path_name']);
        $matches[2] .= "': '";
        $matches[2] .= $this->getNamespaceFromResourceFilename($info['full_path_name_with_hash']);
        $matches[2] .= "'";
      }
    }

    array_shift($matches);
    $js = implode('', $matches);

    return $js;
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
   * Minimizes JavaScript code.
   *
   * @param string $theJsCode The JavaScript code.
   *
   * @return string The minimized JavaScript code.
   */
  private function minimizeJs($theJsCode)
  {
    list($std_out, $std_err) = $this->runProcess($this->myMinifyCommand, $theJsCode);

    if ($std_err) $this->logInfo($std_err);

    return $std_out;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Executes a command and writes data to the standard input and reads data from the standard output and error of the
   * process.
   *
   * @param string $theCommand The command to run.
   * @param string $theInput   The data to send to the process.
   *
   * @return string[] An array with elements: the standard output and the standard error.
   * @throws BuildException
   */
  private function runProcess($theCommand, $theInput)
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
}

//----------------------------------------------------------------------------------------------------------------------
