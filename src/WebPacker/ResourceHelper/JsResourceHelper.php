<?php
declare(strict_types=1);

use SetBased\Helper\ProgramExecution;
use Webmozart\PathUtil\Path;

/**
 * Helper class for JS resources.
 */
class JsResourceHelper implements ResourceHelper, WebPackerInterface
{
  //--------------------------------------------------------------------------------------------------------------------
  use WebPackerTrait;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The size of buffers for reading stdout and stderr of sub-processes.
   *
   * @var int
   */
  const BUFFER_SIZE = 8000;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * PhpSourceHelperJs constructor.
   *
   * @param \WebPackerInterface $parent The parent object.
   */
  public function __construct(\WebPackerInterface $parent)
  {
    $this->initWebPackerTrait($parent);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public static function deriveType(string $content): bool
  {
    unset($content);

    return true;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public static function mustCompress(): bool
  {
    return true;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public function analyze(array $resource1): void
  {
    $lines = explode(PHP_EOL, $resource1['rsr_content'] ?? '');
    foreach ($lines as $i => $line)
    {
      if (preg_match('/^(?<before>[^\'"]*)(?<quote1>[\'"])(?<path>[a-zA-Z0-9_\-.\/]+)(?<quote2>[\'"])(?<after>[^\'"]*)$/',
                     $line,
                     $matches))
      {
        if ($matches['quote1']===$matches['quote2'] && strpos($matches['path'], '/')!==false)
        {
          $resourcePath2 = $this->jsResolveReferredResourcePath($matches['path'], $resource1['rsr_path']);
          $resource2     = $this->store->resourceSearchByPath($resourcePath2);
          if ($resource2!==null)
          {
            $this->task->logVerbose('      found %s (%s:%d)',
                                    Path::makeRelative($resourcePath2, $this->buildPath),
                                    $matches['path'],
                                    $i + 1);

            $this->store->insertRow('ABC_LINK2', ['rsr_id_src'  => $resource1['rsr_id'],
                                                  'rsr_id_rsr'  => $resource2['rsr_id'],
                                                  'lk2_name'    => $matches['path'],
                                                  'lk2_line'    => $i + 1,
                                                  'lk2_matches' => serialize($matches)]);
          }
        }
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public function optimize(array $resource, array $resources): ?string
  {
    if ($resource['rsr_content']===null) return '';

    $lines = explode(PHP_EOL, $resource['rsr_content']);
    foreach ($resources as $resource)
    {
      $matches = unserialize($resource['lk2_matches']);

      $lines[$resource['lk2_line'] - 1] = sprintf('%s%s%s%s%s',
                                                  $matches['before'],
                                                  $matches['quote1'],
                                                  $resource['rsr_uri_optimized'],
                                                  $matches['quote2'],
                                                  $matches['after']);
    }

    $content = implode(PHP_EOL, $lines);

    return $this->minimizeJsCode($content);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public function uriOptimizedPath(array $resource): ?string
  {
    $md5 = md5($resource['rsr_content_optimized'] ?? '');

    return sprintf('/%s/%s.%s', $this->jsDir, $md5, $this->jsExtension);
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
    $this->task->logVerbose('Execute: %s', implode(' ', $command));
    [$output, $ret] = ProgramExecution::exec1($command, null);
    if ($ret!=0)
    {
      $this->task->logError("Error executing '%s':\n%s", implode(' ', $command), implode(PHP_EOL, $output));
    }
    else
    {
      foreach ($output as $line)
      {
        $this->task->logVerbose($line);
      }
    }

    return $output;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the namespace based on the name of a JavaScript file.
   *
   * @param string $resourceFilename The name of the JavaScript file.
   *
   * @return string
   */
  protected function getNamespaceFromResourceFilename(string $resourceFilename): string
  {
    $resourcePath = Path::join([$this->parentResourcePath, $this->jsExtension]);

    $name = Path::makeRelative($resourceFilename, $resourcePath);
    $dir  = Path::getDirectory($name);
    $name = Path::getFilenameWithoutExtension($name);
    $name = Path::getFilenameWithoutExtension($name);

    return Path::join([$dir, $name]);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Minimizes JavaScript code.
   *
   * @param string $code The JS code.
   *
   * @return string The minimized JS code.
   */
  protected function minimizeJsCode(string $code): string
  {
    [$stdOut, $stdErr] = $this->runProcess($this->jsMinifyCommand, $code);

    if ($stdErr) $this->task->logInfo($stdErr);

    return $stdOut;
  }

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
    if ($process===false) $this->task->logError("Unable to span process '%s'", $command);

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

      if (empty($reads) && empty($writes)) break;

      stream_select($reads, $writes, $except, 1);
      if (!empty($reads))
      {
        foreach ($reads as $read)
        {
          if ($read===$pipes[1])
          {
            $data = fread($read, self::BUFFER_SIZE);
            if ($data===false) $this->task->logError("Unable to read standard output from command '%s'", $command);
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
          if ($read===$pipes[2])
          {
            $data = fread($read, self::BUFFER_SIZE);
            if ($data===false) $this->task->logError("Unable to read standard error from command '%s'", $command);
            if ($data==='')
            {
              fclose($pipes[2]);
              unset($read_pipes[1]);
            }
            else
            {
              $std_out .= $data;
              $std_err .= $data;
            }
          }
        }
      }

      if (isset($writes[0]))
      {
        $bytes = fwrite($writes[0], $std_in);
        if ($bytes===false) $this->task->logError("Unable to write to standard input of command '%s'", $command);
        if ($bytes===0)
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
    if ($ret!==0)
    {
      $this->task->logError("Error executing '%s'\n%s", $command, $std_out);
    }

    return [$std_out, $std_err];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the full path of a resource found in another resource.
   *
   * @param string $filename     The relative path found in the referring resource.
   * @param string $referrerPath The full path of the another resource referring to the resource.
   *
   * @return string
   */
  private function jsResolveReferredResourcePath(string $filename, string $referrerPath): string
  {
    if ($filename[0]==='/')
    {
      $resourcePath = Path::join([$this->parentResourcePath, $filename]);
    }
    else
    {
      $baseDir      = Path::getDirectory($referrerPath);
      $resourcePath = Path::makeAbsolute($filename, $baseDir);
    }

    return $resourcePath;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
