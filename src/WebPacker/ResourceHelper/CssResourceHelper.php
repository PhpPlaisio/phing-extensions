<?php
declare(strict_types=1);

use Webmozart\PathUtil\Path;

/**
 * Helper class for CSS resources.
 */
class CssResourceHelper implements ResourceHelper, WebPackerInterface
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
  }  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public function analyze(array $resource1): void
  {
    $lines = explode(PHP_EOL, $resource1['rsr_content'] ?? '');
    foreach ($lines as $i => $line)
    {
      if (preg_match_all('/(?<url>url)\((?<ws1>\s*)(?<quote1>[\'"]?)(?<uri>[a-zA-Z0-9_\-.\/]+)(?<quote2>[\'"]?)(?<ws2>\s*)\)/i',
                         $line,
                         $matches,
                         PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL))
      {
        foreach ($matches as $match)
        {
          if ($match['quote1']===$match['quote2'])
          {
            $resourcePath2 = $this->cssResolveReferredResourcePath($match['uri'], $resource1['rsr_path']);
            $this->task->logVerbose('      found %s (%s:%d)',
                                    Path::makeRelative($resourcePath2, $this->buildPath),
                                    $match['uri'],
                                    $i + 1);

            $resource2 = $this->store->resourceSearchByPath($resourcePath2);
            if ($resource2===null)
            {
              $this->task->logError('File %s not found referred at %s:%d',
                                    $match['uri'],
                                    Path::makeRelative($resource1['rsr_path'], $this->buildPath),
                                    $i + 1);
            }
            else
            {
              $this->store->insertRow('ABC_LINK2', ['rsr_id_src'  => $resource1['rsr_id'],
                                                    'rsr_id_rsr'  => $resource2['rsr_id'],
                                                    'lk2_name'    => $match['uri'],
                                                    'lk2_line'    => $i + 1,
                                                    'lk2_matches' => serialize($match)]);
            }
          }
        }
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public function optimize(array $resource, array $resources): string
  {
    $lines = explode(PHP_EOL, $resource['rsr_content'] ?? '');
    foreach ($resources as $resource)
    {
      $matches = unserialize($resource['lk2_matches']);

      $search  = sprintf('%s(%s%s%s%s%s)',
                         $matches['url'],
                         $matches['ws1'],
                         $matches['quote1'],
                         $matches['uri'],
                         $matches['quote2'],
                         $matches['ws2']);
      $replace = sprintf('%s(%s%s%s)',
                         $matches['url'],
                         $matches['quote1'],
                         $resource['rsr_uri_optimized'],
                         $matches['quote2']);

      $lines[$resource['lk2_line'] - 1] = str_replace($search, $replace, $lines[$resource['lk2_line'] - 1]);
    }

    $content = implode(PHP_EOL, $lines);

    [$std_out, $std_err] = $this->runProcess($this->cssMinifyCommand, $content);

    if ($std_err!=='') $this->task->logError($std_err);

    return $std_out;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public function uriOptimizedPath(array $resource): ?string
  {
    $md5 = md5($resource['rsr_content_optimized'] ?? '');

    return sprintf('/%s/%s.%s', $this->cssDir, $md5, $this->cssExtension);
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
  private function cssResolveReferredResourcePath(string $filename, string $referrerPath): string
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
