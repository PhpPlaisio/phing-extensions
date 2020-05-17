<?php
declare(strict_types=1);

use SetBased\Helper\ProgramExecution;

/**
 * Abstract parent class for optimizing/minimizing resources (i.e. CSS and JS files) and modifying references to those
 * resources.
 */
abstract class OptimizeResourceTask extends ResourceStoreTask
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The size of buffers for reading stdout and stderr of sub-processes.
   *
   * @var int
   */
  const BUFFER_SIZE = 8000;

  /**
   * The list of the web asset classes, interfaces and traits.
   *
   * @var string[]
   */
  protected $webAssetsClasses = [];

  /**
   * Map from the original references to resource files to new references (which includes a hash).
   *
   * @var array
   */
  private $replacePairs = [];

  /**
   * The names of the sources files.
   *
   * @var array
   */
  private $sourceFileNames;

  /**
   * Info about source files.
   *
   * @var array
   */
  private $sourceFilesInfo;

  /**
   * The ID of the fileset with sources.
   *
   * @var string
   */
  private $sourcesFilesetId;

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

    // Prepare all place holders.
    $this->preparePlaceHolders();

    // Replace references to resource files with references to optimized/minimized resource files.
    $this->processingSourceFiles();

    // Compress and rename files with hash.
    $this->processResourceFiles();

    // Create pre-compressed versions of the optimized/minimized resource files.
    if ($this->gzipFlag) $this->gzipCompressOptimizedResourceFiles();
    if ($this->brotliFlag) $this->brotliCompressOptimizedResourceFiles();

    // Remove original resource files that are optimized/minimized.
    $this->unlinkResourceFiles();
  }

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
   * Executes an external program.
   *
   * @param string[] $command The command as array.
   *
   * @return string[] The output of the command.
   */
  protected function execCommand(array $command): array
  {
    $this->logVerbose('Execute: %s', implode(' ', $command));
    [$output, $ret] = ProgramExecution::exec1($command, null);
    if ($ret!=0)
    {
      foreach ($output as $line)
      {
        $this->logInfo($line);
      }
      $this->logError("Error executing '%s'", implode(' ', $command));
    }
    else
    {
      foreach ($output as $line)
      {
        $this->logVerbose($line);
      }
    }

    return $output;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns an array with classes and namespaces defined in PHP code.
   *
   * @param string $phpCode The PHP code.
   *
   * @return array
   */
  protected function getClasses(string $phpCode): array
  {
    $tokens = token_get_all($phpCode);

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
      if (is_array($token) && in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT]))
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
          throw new \LogicException('Bracketed syntax for namespace not supported');
        }
        else
        {
          throw new \LogicException("Unexpected token %s", print_r($token, true));
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
   * Get info about all source, resource files and directories.
   */
  protected function prepareProjectData(): void
  {
    parent::prepareProjectData();

    // Get file list form the project by fileset ID.
    $sources               = $this->getProject()->getReference($this->sourcesFilesetId);
    $this->sourceFileNames = $sources->getDirectoryScanner($this->getProject())->getIncludedFiles();
    $suc                   = ksort($this->sourceFileNames);
    if ($suc===false) $this->logError("ksort failed");
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
  abstract protected function processPhpSourceFile(string $filename, string $phpCode): string;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Executes a command and writes data to the standard input and reads data from the standard output and error of the
   * process.
   *
   * @param string $command The command to run.
   * @param string $input   The data to send to the process.
   *
   * @return string[] An array with two elements: the standard output and the standard error.
   *
   * @throws \BuildException
   */
  protected function runProcess(string $command, string $input): array
  {
    $descriptor_spec = [0 => ["pipe", "r"],
                        1 => ["pipe", "w"],
                        2 => ["pipe", "w"]];

    $process = proc_open($command, $descriptor_spec, $pipes);
    if ($process===false) $this->logError("Unable to span process '%s'", $command);

    $write_pipes = [$pipes[0]];
    $read_pipes  = [$pipes[1], $pipes[2]];
    $std_out     = '';
    $std_err     = '';
    $std_in      = $input;
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
            if ($data===false) $this->logError("Unable to read standard output from command '%s'", $command);
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
            if ($data===false) $this->logError("Unable to read standard error from command '%s'", $command);
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
        if ($bytes===false) $this->logError("Unable to write to standard input of command '%s'", $command);
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
      $this->logError("Error executing '%s'", $command);
    }

    return [$std_out, $std_err];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Compresses optimized/minimized resource files with Brotli.
   *
   * @throws \BuildException
   */
  private function brotliCompressOptimizedResourceFiles(): void
  {
    $this->logInfo('Brotli compressing files');

    foreach ($this->getHashedResourceFilenames() as $resourcePath)
    {
      $brotliPath = $resourcePath.'.br';

      $this->logVerbose("Brotli compressing file '%s' to '%s'", $resourcePath, $brotliPath);

      $command = [$this->brotliPath, '--quality=11', '--keep', $resourcePath];
      $this->execCommand($command);

      if (filesize($resourcePath)<filesize($brotliPath))
      {
        $this->logVerbose('Compressed file larger than original file');

        unlink($brotliPath);
      }
      else
      {
        if ($this->preserveModificationTime)
        {
          $info = $this->getResourceInfoByHash($resourcePath);
          $this->setModificationTime($brotliPath, $info['mtime']);
        }

        $this->setFilePermissions($brotliPath, $resourcePath);
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   *  Gets full path for each source file in the source fileset.
   */
  private function getInfoSourceFiles(): void
  {
    $this->logVerbose('Get source files info');

    // Get the base dir of the sources.
    $dir = $this->getProject()->getReference($this->sourcesFilesetId)->getDir($this->getProject());

    foreach ($this->sourceFileNames as $theFileName)
    {
      $filename                          = $dir.'/'.$theFileName;
      $real_path                         = realpath($filename);
      $this->sourceFilesInfo[$real_path] = $filename;
    }

    $suc = ksort($this->sourceFilesInfo);
    if ($suc===false) $this->logError("ksort failed");
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the maximum mtime of a sources file and its referenced optimized/minimized resource files.
   *
   * @param string $sourceFilename The name of the source file.
   * @param string $content        The content of the source file with renamed references to resource files.
   *
   * @return int The max mtime.
   */
  private function getMaxModificationTime(string $sourceFilename, string $content): int
  {
    $times = [];

    $time = filemtime($sourceFilename);
    if ($time===false) $this->logError("Unable to get mtime of file '%s'", $sourceFilename);
    $times[] = $time;

    $resource_files_info = $this->getResourceFilesInSource($content);
    foreach ($resource_files_info as $resource_file_info)
    {
      $info = $this->getResourceInfoByHash($resource_file_info['full_path_name_with_hash']);
      $time = $info['mtime'];
      if ($time===false) $this->logError("Unable to get mtime for file '%s'", $resource_file_info);
      $times[] = $time;
    }

    return max($times);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns an array with info about resource files referenced from a source file.
   *
   * @param string $sourceFileContent Content of updated source file.
   *
   * @return array
   */
  private function getResourceFilesInSource(string $sourceFileContent): array
  {
    $resource_files = [];
    foreach ($this->getResourcesInfo() as $file_info)
    {
      if (strpos($sourceFileContent, $file_info['path_name_in_sources_with_hash'])!==false)
      {
        $resource_files[] = $file_info;
      }
    }

    return $resource_files;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Compresses optimized/minimized resource files with gzip.
   *
   * @throws \BuildException
   */
  private function gzipCompressOptimizedResourceFiles(): void
  {
    $this->logInfo('Gzip compressing files');

    foreach ($this->getHashedResourceFilenames() as $resourcePath)
    {
      $gzipPath = $resourcePath.'.gz';

      $this->logVerbose("Gzip compressing file '%s' to '%s'", $resourcePath, $gzipPath);

      // Get data from the file.
      $data_opt = file_get_contents($resourcePath);
      if ($data_opt===false)
      {
        $this->logError("Can not read the file '%s' or file does not exist", $resourcePath);
      }

      // Compress data with gzip
      $data_gzip = gzencode($data_opt, 9);
      if ($data_gzip===false)
      {
        $this->logError("Can not write the file '%s' or file does not exist", $gzipPath);
      }

      if (strlen($data_gzip)<strlen($data_opt))
      {
        // Write data to the file.
        $status = file_put_contents($gzipPath, $data_gzip);
        if ($status===false)
        {
          $this->logError("Unable to write to file '%s", $gzipPath);
        }

        // If required preserve mtime.
        if ($this->preserveModificationTime)
        {
          $info = $this->getResourceInfoByHash($resourcePath);
          $this->setModificationTime($gzipPath, $info['mtime']);
        }

        $this->setFilePermissions($gzipPath, $resourcePath);
      }
      else
      {
        $this->logVerbose('Compressed file larger than original file');
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Prepares place holders with resource file names and references to optimized/minimized resource file names (with
   * includes a hash).
   */
  private function preparePlaceHolders(): void
  {
    $this->logVerbose('Prepare place holders');

    foreach ($this->getResourcesInfo() as $file_info)
    {
      $this->replacePairs["'".$file_info['path_name_in_sources']."'"] = "'".$file_info['path_name_in_sources_with_hash']."'";
      $this->replacePairs['"'.$file_info['path_name_in_sources'].'"'] = '"'.$file_info['path_name_in_sources_with_hash'].'"';

      if (isset($file_info['path_name_in_sources_alternative']))
      {
        $this->replacePairs["'".$file_info['path_name_in_sources_alternative']."'"] = "'".$file_info['path_name_in_sources_with_hash']."'";
        $this->replacePairs['"'.$file_info['path_name_in_sources_alternative'].'"'] = '"'.$file_info['path_name_in_sources_with_hash'].'"';
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Minimizes/optimizes the resource files and removes original resources files from the build dir.
   */
  private function processResourceFiles(): void
  {
    // Save all optimized resource files.
    $this->saveOptimizedResourceFiles();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Replaces references to resource files in source files with the hashed filename op the optimized/minimized
   * resources.
   */
  private function processingSourceFiles(): void
  {
    $this->logVerbose('Replace references to resource files with references to optimized/minimized resource files');

    foreach ($this->sourceFilesInfo as $source_filename)
    {
      $this->logVerbose('Processing %s', $source_filename);

      $content = file_get_contents($source_filename);
      if ($content===false) $this->logError("Unable to read file '%s'", $source_filename);

      $new_content = $content;

      if (strncmp($content, '<?php', 5)===0)
      {
        // Source is a PHP file.
        $new_content = $this->processPhpSourceFile($source_filename, $new_content);
      }

      $new_content = strtr($new_content, $this->replacePairs);

      if ($content!=$new_content)
      {
        $time = null;

        // If required determine the latest modification time of the source file and its referenced resource files.
        if ($this->preserveModificationTime)
        {
          $time = $this->getMaxModificationTime($source_filename, $new_content);
        }

        // Write sources file with modified references to resource files.
        $status = file_put_contents($source_filename, $new_content);
        if ($status===false) $this->logError("Updating file '%s' failed", $source_filename);
        $this->logInfo("Updated file '%s'", $source_filename);

        // If required set the mtime to the latest modification time of the source file and its referenced resource
        // files.
        if ($this->preserveModificationTime)
        {
          $status = touch($source_filename, $time);
          if ($status===false) $this->logError("Unable to set mtime for file '%s'", $source_filename);
        }
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------