<?php
//----------------------------------------------------------------------------------------------------------------------
require_once 'OptimizeResourceTask.php';

//----------------------------------------------------------------------------------------------------------------------
/**
 * Class for optimizing and combining CSS files.
 */
class OptimizeCssTask extends OptimizeResourceTask
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Enable\disable Compress the CSS code (true\false)
   *
   * @var bool
   */
  protected $myMinimize;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * OptimizeCssTask constructor.
   */
  public function __construct()
  {
    parent::__construct('.css');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute $myCssMinimize.
   *
   * @param bool $theMinimize
   */
  public function setMinimize($theMinimize = true)
  {
    $this->myMinimize = (boolean)$theMinimize;
  }
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Minimizes CSS code.
   *
   * @param string $theResource The CSS code.
   *
   * @param        $theFullPathName
   *
   * @return string The minimized CSS code.
   */
  protected function minimizeResource($theResource, $theFullPathName)
  {
    $theResource = $this->convertRelativePaths($theResource, $theFullPathName);

    // Compress the CSS code.
    if ($this->myMinimize && isset($theFullPathName))
    {
      $compressor = new \CSSmin(false);

      return $compressor->run($theResource);
    }
    else
    {
      return $theResource;
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Replaces in PHP code calls to methods {@link \SetBased\Abc\Page\Page::cssAppendPageSpecificSource) and
   * {@link \SetBased\Abc\Page\Page::cssAppendSource) with calls to
   * {@link \SetBased\Abc\Page\Page::cssOptimizedAppendSource} and replaces multiple consecutive calls to
   * {@link \SetBased\Abc\Page\Page::cssOptimizedAppendSource} single call to
   * {@link \SetBased\Abc\Page\Page::cssOptimizedAppendSource} and combines the multiple CSS files into a single CCS
   * file.
   *
   * @param string $thePhpCode The PHP code.
   *
   * @return string The modified PHP code.
   */
  protected function processPhpSourceFile($thePhpCode)
  {
    // Methods for including CCS files.
    $methods = ['cssAppendSource', 'cssAppendPageSpecificSource', 'cssOptimizedAppendSource'];

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
      // The PHP code includes CSS files.
      $thePhpCode = $this->processPhpSourceFileReplaceMethod($thePhpCode);

      $thePhpCode = $this->processPhpSourceFileCombine($thePhpCode);
    }

    return $thePhpCode;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Replaces multiple consecutive calls to {@link \SetBased\Abc\Page\Page::cssOptimizedAppendSource} in PHP code with a
   * single call to {@link \SetBased\Abc\Page\Page::cssOptimizedAppendSource} and combines the multiple CSS files into a
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
      // Find calls to cssOptimizedAppendSource.
      if (preg_match('/^(.*)(\$this->)(cssOptimizedAppendSource)(\(\s*[\'"])([a-zA-Z0-9_\-\.\/]+)([\'"]\s*\)\s*;)(.*)$/',
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
   * Replaces calls to methods {@link \SetBased\Abc\Page\Page::cssAppendPageSpecificSource) and
   * {@link \SetBased\Abc\Page\Page::cssAppendSource) with calls to
   * {@link \SetBased\Abc\Page\Page::cssOptimizedAppendSource}.
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

      // Replace calls to cssAppendPageSpecificSource with cssOptimizedAppendSource.
      if (preg_match('/^(\s*)(\$this->)(cssAppendPageSpecificSource)(\(\s*)(__CLASS__)(\s*\)\s*;)(.*)$/',
                     $line,
                     $matches))
      {
        $file_name = str_replace('\\', '/', $current_class).$this->myExtension;
        $full_path = $this->myResourceDirFullPath.'/'.$file_name;
        if (!file_exists($full_path))
        {
          $this->logError("File '%s' not found.", $full_path);
        }
        else
        {
          $real_path  = realpath($full_path);
          $matches[3] = 'cssOptimizedAppendSource';
          $matches[5] = "'".$this->getResourceInfo($real_path)['path_name_in_sources_with_hash']."'";

          array_shift($matches);
          $lines[$i] = implode('', $matches);
        }
      }
      elseif (preg_match('/^(\s*)(\$this->)(cssAppendSource)(\(\s*[\'"])([a-zA-Z0-9_\-\.\/]+)([\'"]\s*\)\s*;)(.*)$/',
                         $line,
                         $matches))
      {
        $file_name = $matches[5];
        $full_path = $this->myParentResourceDirFullPath.'/'.$file_name;
        print_r("\n$full_path\n");
        if (!file_exists($full_path))
        {
          $this->logError("File '%s' not found.", $full_path);
        }
        else
        {
          $real_path  = realpath($full_path);
          $matches[3] = 'cssOptimizedAppendSource';
          $matches[5] = $this->getResourceInfo($real_path)['path_name_in_sources_with_hash'];

          array_shift($matches);
          $lines[$i] = implode('', $matches);
        }
      }
    }

    return implode("\n", $lines);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * In CSS code replace relative paths with absolute paths.
   *
   * @param string $theCss The CSS code.
   *
   * @param        $theFullPathName
   *
   * @return string The modified CSS code.
   */
  private function convertRelativePaths($theCss, $theFullPathName)
  {
    // @todo fix URLs like url(test/test(1).jpg)

    $ccs = preg_replace_callback('/(url\([\'"]?)(([^()]|(?R))+)([\'"]?\))/i', function ($matches) use ($theFullPathName)
    {
      return $matches[1].\SetBased\Abc\Helper\Url::combine($this->getPathInResources($theFullPathName), $matches[2]).$matches[4];
    }
      , $theCss);

    return $ccs;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Replaces a group of multiple consecutive calls to {@link \SetBased\Abc\Page\Page::cssOptimizedAppendSource} in PHP
   * code with a single call.
   *
   * @param string[]   $theLines The lines of the PHP code.
   * @param int[]      $theGroup The group of of multiple consecutive calls to
   *                             {@link \SetBased\Abc\Page\Page::cssOptimizedAppendSource}
   * @param string[][] $theCalls The matches from preg_match.
   */
  private function processPhpSourceFileCombineGroup(&$theLines, $theGroup, $theCalls)
  {
    $files                    = [];
    $file_info                = [];
    $file_info['content_opt'] = '';
    foreach ($theGroup as $i)
    {
      $filename = $this->myParentResourceDirFullPath.$theCalls[$i][5];
      $code     = file_get_contents($filename);
      if ($code===false) $this->logError("Unable to read file '%s'.", $filename);

      $file_info['content_opt'] .= $code;
      $files[] = $filename;
    }
    $file_info = $this->store($file_info['content_opt'], null);

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
  }
}

//----------------------------------------------------------------------------------------------------------------------
