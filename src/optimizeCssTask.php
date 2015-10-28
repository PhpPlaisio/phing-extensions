<?php
//----------------------------------------------------------------------------------------------------------------------
require 'optimizeResourceTask.php';

//----------------------------------------------------------------------------------------------------------------------
/**
 * Class for optimizing and combining CSS files.
 */
class optimizeCssTask extends optimizeResourceTask
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Optimizes/minimizes all CSS files (in the resource file set).
   */
  protected function optimizeResourceFile()
  {
    foreach ($this->myResourceFilesInfo as &$file_info)
    {
      $this->logInfo("Minimizing '%s'.", $file_info['full_path_name']);

      $css_raw    = file_get_contents($file_info['full_path_name']);
      $compressor = new CSSmin(false);
      $css_opt    = $compressor->run($css_raw);

      $file_info['hash']        = md5($css_opt);
      $file_info['content_raw'] = $css_raw;
      $file_info['content_opt'] = $css_opt;
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  protected function processPhpSourceFile($thePhpCode)
  {
    // Methods for including CCS files.
    $methods = ['appendCssSource', 'appendPageSpecificCssSource', 'appendOptimizedCssSource'];

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

      $thePhpCode = $this->processPhpSourceFileCombine($thePhpCode);
    }

    return $thePhpCode;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Replaces multiple consecutive calls to {@link \SetBased\Abc\Page\Page::appendOptimizedCssSource} in PHP code with a
   * single call to {@link \SetBased\Abc\Page\Page::appendOptimizedCssSource} and combines the multiple CSS files into a
   * single CCS file.
   *
   * @param string $thePhpCode The PHP code.
   *
   * @return string The modified PHP code.
   */
  protected function processPhpSourceFileCombine($thePhpCode)
  {
    $lines    = explode("\n", $thePhpCode);
    $calls    = [];
    $groups   = [];
    $group    = [];
    $previous = -1;
    foreach ($lines as $i => $line)
    {
      // FInd calls to appendOptimizedCssSource with appendOptimizedCssSource.
      if (preg_match('/^(.*)(\$this->)(appendOptimizedCssSource)(\(\s*[\'"])([a-zA-Z0-9_\-\.\/]+)([\'"]\s*\)\s*;)(.*)$/',
                     $line,
                     $matches))
      {
        $calls[$i] = $matches;

        if ($previous + 1!=$i)
        {
          if ($group)
          {
            $groups[] = $group;
            $group    = [];
          }
        }

        $group[]  = $i;
        $previous = $i;
      }
    }
    if ($group) $groups[] = $group;

    // Combine groups with 2 or more CSS files to one file.
    foreach ($groups as $group)
    {
      if (count($group)>1)
      {
        $this->processPhpSourceFileCombineGroup($lines, $group, $calls);
      }
    }

    return implode("\n", $lines);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Replaces calls to methods {@link \SetBased\Abc\Page\Page::appendPageSpecificCssSource) and
   * {@link \SetBased\Abc\Page\Page::appendCssSource) with calls to
   * {@link \SetBased\Abc\Page\Page::appendOptimizedCssSource}.
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

      // Replace calls to appendPageSpecificCssSource with appendOptimizedCssSource.
      if (preg_match('/^(.*)(\$this->)(appendPageSpecificCssSource)(\(\s*)(__CLASS__)(\s*\)\s*;)(.*)$/',
                     $line,
                     $matches))
      {
        $file_name = str_replace('\\', '/', $current_class).'.css';
        $full_path = $this->myResourceDirFullPath.'/'.$file_name;
        if (!file_exists($full_path))
        {
          $this->logError("File '%s' not found.", $full_path);
        }
        else
        {
          $real_path  = realpath($full_path);
          $matches[3] = 'appendOptimizedCssSource';
          $matches[5] = "'".$this->myResourceFilesInfo[$real_path]['path_name_in_sources_with_hash']."'";

          array_shift($matches);
          $lines[$i] = implode('', $matches);
        }
      }
      elseif (preg_match('/^(.*)(\$this->)(appendCssSource)(\(\s*[\'"])([a-zA-Z0-9_\-\.\/]+)([\'"]\s*\)\s*;)(.*)$/',
                         $line,
                         $matches))
      {
        $file_name = $matches[5];
        $full_path = $this->myParentResourceDirFullPath.'/'.$file_name;
        if (!file_exists($full_path))
        {
          $this->logError("File '%s' not found.", $full_path);
        }
        else
        {
          $real_path  = realpath($full_path);
          $matches[3] = 'appendOptimizedCssSource';
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
   * Replaces a group of multiple consecutive calls to {@link \SetBased\Abc\Page\Page::appendOptimizedCssSource} in PHP
   * code with a single call.
   *
   * @param string[]   $theLines The lines of the PHP code.
   * @param int[]      $theGroup The group of of multiple consecutive calls to
   *                             {@link \SetBased\Abc\Page\Page::appendOptimizedCssSource}
   * @param string[][] $theCalls The matches from preg_match.
   */
  private function processPhpSourceFileCombineGroup(&$theLines, $theGroup, $theCalls)
  {
    $files                    = [];
    $file_info                = [];
    $file_info['content_opt'] = '';
    foreach ($theGroup as $i)
    {
      $filename = $this->myParentResourceDirFullPath.'/'.$theCalls[$i][5];
      $code     = file_get_contents($filename);
      if ($code===false) $this->logError("Unable to read file '%s'.", $filename);

      $file_info['content_opt'] .= $code;
      $files[] = $filename;
    }

    // Compute hash of the combined code.
    $file_info['hash'] = md5($file_info['content_opt']);

    // Compute the ordinal of the hash code.
    if (!isset($this->myHashCount[$file_info['hash']]))
    {
      $this->myHashCount[$file_info['hash']] = 0;
    }
    $file_info['ordinal'] = $this->myHashCount[$file_info['hash']];
    $this->myHashCount[$file_info['hash']]++;

    // Set the full path with hash of the combined file.
    $file_info['full_path_name_with_hash']       = $this->myResourceDirFullPath.'/'.
      $file_info['hash'].'.'.$file_info['ordinal'].'.css';
    $file_info['path_name_in_sources_with_hash'] = $this->getPathInSources($file_info['full_path_name_with_hash']);

    // Save the combined code.
    $bytes = file_put_contents($file_info['full_path_name_with_hash'], $file_info['content_opt']);
    if ($bytes===false) $this->logError("Unable to write to file '%s'.", $file_info['full_path_name_with_hash']);

    // Take the permissions from the first include file.
    $time = fileperms($files[0]);
    if ($time===false) $this->logError("Unable to get mode of file '%s'.", $files[0]);
    $file_info['mode'] = $time;

    // Replace the multiple calls with one call in the PHP code.
    $first = true;
    foreach ($theGroup as $i)
    {
      if ($first)
      {
        $matches    = $theCalls[$i];
        $matches[5] = $file_info['path_name_in_sources_with_hash'];
        array_shift($matches);
        $theLines[$i] = implode('', $matches);

        $first = false;
      }
      else
      {
        $theLines[$i] = '';
      }
    }

    if ($this->myPreserveModificationTime)
    {
      // If required determine the latest modification time of the include files.
      $mtime = 0;
      foreach ($files as $filename)
      {
        $time = filemtime($filename);
        if ($time===false) $this->logError("Unable to get mtime of file '%s'.", $filename);
        $mtime = max($mtime, $time);
      }

      // Set mtime of the combined include file.
      $status = touch($file_info['full_path_name_with_hash'], $mtime);
      if ($status===false)
      {
        $this->logError("Unable to set mtime of file '%s' to '%s", $file_info['full_path_name_with_hash'], $mtime);
      }
    }

    $this->myResourceFilesInfo[] = $file_info;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
