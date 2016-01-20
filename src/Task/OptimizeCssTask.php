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
   * Enables/disables compression of CSS. This flag is for testing purposes only.
   *
   * @var bool
   */
  protected $myMinimize = true;

  /**
   * All methods for including CSS sources.
   *
   * @var array
   */
  private $myMethods = ['cssAppendSource',
                        'cssAppendPageSpecificSource',
                        'cssOptimizedAppendSource',
                        'cssStaticAppendClassSource',
                        'cssStaticAppendSource',
                        'cssStaticOptimizedAppendSource'];

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
  public function setMinimize($theMinimize)
  {
    $this->myMinimize = (boolean)$theMinimize;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Minimizes CSS code.
   *
   * @param string $theResource     The CSS code.
   * @param string $theFullPathName The full pathname of the CSS file.
   *
   * @return string The minimized CSS code.
   */
  protected function minimizeResource($theResource, $theFullPathName)
  {
    $resource = $theResource;

    // If $theFullPathName is not set $theResource is concatenation of 2 or more optimized CSS file. There is no need to
    // convert relative paths and minimized $theResource again. Moreover, it is not possible to convert relative paths
    // since $theResource can be a concatenation of CSS files from different subdirectories.
    if (isset($theFullPathName))
    {
      $resource = $this->convertRelativePaths($theResource, $theFullPathName);

      // Compress the CSS code.
      if ($this->myMinimize)
      {
        $compressor = new \CSSmin(false);

        $resource = $compressor->run($resource);
      }
    }

    return $resource;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Replaces in PHP code calls to methods:
   * <ul>
   * <li>{@link \SetBased\Abc\Page\Page::cssAppendSource) and
   * <li>{@link \SetBased\Abc\Page\Page::cssAppendPageSpecificSource)
   * <li>{@link \SetBased\Abc\Page\Page::cssStaticAppendClassSource}
   * <li>{@link \SetBased\Abc\Page\Page::cssStaticAppendSource}
   * </ul>
   * with the appropriate optimized method. Also, combines the multiple CSS files into a single CCS file.
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
      // The PHP code includes CSS files.
      $thePhpCode = $this->processPhpSourceFileReplaceMethod($theFilename, $thePhpCode);

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

      // Don't process the class that defines the css* methods.
      if ($current_class=='SetBased\\Abc\\Page\\Page') continue;

      // Replace calls to cssAppendPageSpecificSource with cssOptimizedAppendSource.
      if (preg_match('/^(\s*)(\$this->)(cssAppendPageSpecificSource)(\(\s*)(__CLASS__)(\s*\)\s*;)(.*)$/',
                     $line,
                     $matches))
      {
        $lines[$i] = $this->processPhpSourceFileReplaceMethodHelper($matches,
                                                                    'cssOptimizedAppendSource',
                                                                    $current_class);
      }

      // Replace calls to cssAppendSource with cssOptimizedAppendSource.
      elseif (preg_match('/^(\s*)(\$this->)(cssAppendSource)(\(\s*[\'"])([a-zA-Z0-9_\-\.\/]+)([\'"]\s*\)\s*;)(.*)$/',
                         $line,
                         $matches))
      {
        $lines[$i] = $this->processPhpSourceFileReplaceMethodHelper($matches, 'cssOptimizedAppendSource');
      }

      // Replace calls to cssStaticAppendClassSource with cssStaticOptimizedAppendSource.
      elseif (preg_match('/^(\s*)(Page::)(cssStaticAppendClassSource)(\(\s*)(__CLASS__)(\s*\)\s*;)(.*)$/',
                         $line,
                         $matches))
      {
        $lines[$i] = $this->processPhpSourceFileReplaceMethodHelper($matches,
                                                                    'cssStaticOptimizedAppendSource',
                                                                    $current_class);
      }

      // Replace calls to cssStaticAppendSource with cssStaticOptimizedAppendSource.
      elseif (preg_match('/^(\s*)(Page::)(cssStaticAppendSource)(\(\s*[\'"])([a-zA-Z0-9_\-\.\/]+)([\'"]\s*\)\s*;)(.*)$/',
                         $line,
                         $matches))
      {
        $lines[$i] = $this->processPhpSourceFileReplaceMethodHelper($matches, 'cssStaticOptimizedAppendSource');
      }

      // Test for invalid usage of methods for including CSS files.
      else
      {
        foreach ($this->myMethods as $method)
        {
          if (preg_match("/(->|::)($method)(\\()/", $line))
          {
            $this->logError("Unexpected usage of method '%s' at line %s:%d.", $method, $theFilename, $i + 1);
          }
        }
      }
    }

    return implode("\n", $lines);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * In CSS code replace relative paths with absolute paths.
   *
   * @param string $theCss          The CSS code.
   * @param string $theFullPathName The full pathname of the CSS file.
   *
   * @return string The modified CSS code.
   */
  private function convertRelativePaths($theCss, $theFullPathName)
  {
    // @todo fix URLs like url(test/test(1).jpg)

    $ccs = preg_replace_callback('/(url\([\'"]?)(([^()]|(?R))+)([\'"]?\))/i',
      function ($matches) use ($theFullPathName)
      {
        return $matches[1].\SetBased\Abc\Helper\Url::combine($this->getPathInResources($theFullPathName),
                                                             $matches[2]).$matches[4];
      },
                                 $theCss);

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
      $info     = $this->getResourceInfoByHash($filename);
      $code     = $info['content_opt'];

      $file_info['content_opt'] .= $code;
      $files[] = $filename;
    }
    $file_info = $this->store($file_info['content_opt'], null, $files, 'full_path_name_with_hash');

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

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Helper function for {@link processPhpSourceFileReplaceMethodHelper}.
   *
   * @param string[] $theMatches         The matches as returned by preg_match.
   * @param string   $theOptimizedMethod The appropriate optimized method.
   * @param string   $theClassName       The current class name of the PHP code.
   *
   * @return string
   * @throws BuildException
   */
  private function processPhpSourceFileReplaceMethodHelper($theMatches, $theOptimizedMethod, $theClassName = null)
  {
    if (isset($theClassName))
    {
      $file_name = str_replace('\\', '/', $theClassName).$this->myExtension;
      $full_path = $this->myResourceDirFullPath.'/'.$file_name;
    }
    else
    {
      $file_name = $theMatches[5];
      $full_path = $this->myParentResourceDirFullPath.'/'.$file_name;
    }

    if (!file_exists($full_path))
    {
      $this->logError("File '%s' not found.", $full_path);
    }

    $real_path     = realpath($full_path);
    $theMatches[3] = $theOptimizedMethod;

    if (isset($theClassName))
    {
      $theMatches[5] = "'".$this->getResourceInfo($real_path)['path_name_in_sources_with_hash']."'";
    }
    else
    {
      $theMatches[5] = $this->getResourceInfo($real_path)['path_name_in_sources_with_hash'];
    }

    array_shift($theMatches);

    return implode('', $theMatches);
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
