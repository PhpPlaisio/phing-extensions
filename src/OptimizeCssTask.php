<?php
declare(strict_types=1);

use Plaisio\Helper\Url;

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
  protected $minimize = true;

  /**
   * All methods for including CSS sources.
   *
   * @var array
   */
  private $methods = ['cssAppendSource',
                      'cssAppendClassSpecificSource',
                      'cssOptimizedAppendSource'];

  /**
   * The command to minify CSS.
   *
   * @var string
   */
  private $minifyCommand = '/usr/bin/csso';

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
   * Setter for XML attribute combineCommand.
   *
   * @param string $minifyCommand The command to run csso.
   */
  public function setMinifyCommand(string $minifyCommand): void
  {
    $this->minifyCommand = $minifyCommand;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute $myCssMinimize.
   *
   * @param bool $minimize
   */
  public function setMinimize(bool $minimize): void
  {
    $this->minimize = $minimize;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Minimizes CSS code.
   *
   * @param string      $resource     The CSS code.
   * @param string|null $fullPathName The full pathname of the CSS file.
   *
   * @return string The minimized CSS code.
   */
  protected function minimizeResource(string $resource, ?string $fullPathName): string
  {
    // If $theFullPathName is not set $resource is concatenation of 2 or more optimized CSS file. There is no need to
    // convert relative paths and minimized $resource again. Moreover, it is not possible to convert relative paths
    // since $resource can be a concatenation of CSS files from different subdirectories.
    if (isset($fullPathName))
    {
      $css = $this->convertRelativePaths($resource, $fullPathName);

      [$std_out, $std_err] = $this->runProcess($this->minifyCommand, $css);

      if ($std_err) $this->logInfo($std_err);

      $ret = $std_out;
    }
    else
    {
      $ret = $resource;
    }

    return $ret;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Replaces in PHP code calls to methods:
   * <ul>
   * <li>{@link Plaisio\WebAssets\WebAssets::cssAppendSource) and
   * <li>{@link Plaisio\WebAssets\WebAssets::cssAppendClassSpecificSource)
   * </ul>
   * with the appropriate optimized method. Also, combines the multiple CSS files into a single CCS file.
   *
   * @param string $filename The filename with the PHP code.
   * @param string $phpCode  The PHP code.
   *
   * @return string The modified PHP code.
   */
  protected function processPhpSourceFile(string $filename, string $phpCode): string
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
      // The PHP code includes CSS files.
      $phpCode = $this->processPhpSourceFileReplaceMethod($filename, $phpCode);

      $phpCode = $this->processPhpSourceFileCombine($phpCode);
    }

    return $phpCode;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Replaces multiple consecutive calls to {@link Plaisio\WebAssets\WebAssets::cssOptimizedAppendSource} in PHP
   * code with a single call to {@link Plaisio\WebAssets\WebAssets::cssOptimizedAppendSource} and combines the
   * multiple CSS files into a single CCS file.
   *
   * @param string $phpCode The PHP code.
   *
   * @return string The modified PHP code.
   */
  protected function processPhpSourceFileCombine(string $phpCode): string
  {
    $lines    = explode("\n", $phpCode);
    $calls    = [];
    $groups   = [];
    $group    = [];
    $previous = -1;
    foreach ($lines as $i => $line)
    {
      // Find calls to cssOptimizedAppendSource.
      if (preg_match('/^(.*)(Nub::\$nub->assets->)(cssOptimizedAppendSource)(\(\s*[\'"])([a-zA-Z0-9_\-.\/]+)([\'"]\s*\)\s*;)(.*)$/',
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
   * Replaces calls to methods {@link Plaisio\WebAssets\WebAssets::cssAppendPageSpecificSource) and
   * {@link Plaisio\WebAssets\WebAssets::cssAppendSource) with calls to
   * {@link Plaisio\WebAssets\WebAssets::cssOptimizedAppendSource}.
   *
   * @param string $filename The filename with the PHP code.
   * @param string $phpCode  The PHP code.
   *
   * @return string The modified PHP code.
   */
  protected function processPhpSourceFileReplaceMethod(string $filename, string $phpCode): string
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

      // Don't process the class that defines the css* methods.
      if (in_array($current_class, $this->webAssetsClasses)) continue;

      // Replace calls to cssAppendPageSpecificSource with cssOptimizedAppendSource.
      if (preg_match('/^(\s*)(Nub::\$nub->assets->)(cssAppendClassSpecificSource)(\(\s*)(__CLASS__|__TRAIT__)(\s*\)\s*;)(.*)$/',
                     $line,
                     $matches))
      {
        $lines[$i] = $this->processPhpSourceFileReplaceMethodHelper($matches,
                                                                    'cssOptimizedAppendSource',
                                                                    $current_class);
      }

      // Replace calls to cssAppendSource with cssOptimizedAppendSource.
      elseif (preg_match('/^(\s*)(Nub::\$nub->assets->)(cssAppendSource)(\(\s*[\'"])([a-zA-Z0-9_\-.\/]+)([\'"]\s*\)\s*;)(.*)$/',
                         $line,
                         $matches))
      {
        $lines[$i] = $this->processPhpSourceFileReplaceMethodHelper($matches, 'cssOptimizedAppendSource');
      }

      // Test for invalid usage of methods for including CSS files.
      else
      {
        foreach ($this->methods as $method)
        {
          if (preg_match("/(->|::)($method)(\\()/", $line))
          {
            $this->logError("Unexpected usage of method '%s' at line %s:%d", $method, $filename, $i + 1);
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
   * @param string $css          The CSS code.
   * @param string $fullPathName The full pathname of the CSS file.
   *
   * @return string The modified CSS code.
   */
  private function convertRelativePaths(string $css, string $fullPathName): string
  {
    // Note: URLs like url(test/test(1).jpg) i.e. URL with ( or ) in name, or not supported.

    // The pcre.backtrack_limit option can trigger a NULL return, with no errors. To prevent we reach this limit we
    // split the CSS into an array of lines.
    $lines = explode("\n", $css);

    $lines = preg_replace_callback('/(url\([\'"]?)(([^()]|(?R))+)([\'"]?\))/i',
      function ($matches) use ($fullPathName) {
        return $matches[1].Url::combine($this->getPathInResources($fullPathName), $matches[2]).$matches[4];
      },
                                   $lines);

    if ($lines===null)
    {
      $this->logError("Converting relative paths failed for '%s'", $fullPathName);
    }

    return implode("\n", $lines);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Replaces a group of multiple consecutive calls to {@link
   * Plaisio\WebAssets\WebAssets::cssOptimizedAppendSource} in PHP code with a single call.
   *
   * @param string[]   $lines    The lines of the PHP code.
   * @param int[]      $group    The group of of multiple consecutive calls to
   *                             {@link Plaisio\WebAssets\WebAssets::cssOptimizedAppendSource}
   * @param string[][] $calls    The matches from preg_match.
   */
  private function processPhpSourceFileCombineGroup(array &$lines, array $group, array $calls): void
  {
    $files                    = [];
    $file_info                = [];
    $file_info['content_opt'] = '';
    foreach ($group as $i)
    {
      $filename = $this->parentResourceDirFullPath.$calls[$i][5];

      $this->logVerbose('Combining %s', $filename);

      $info = $this->getResourceInfoByHash($filename);
      $code = $info['content_opt'];

      $file_info['content_opt'] .= $code;
      $files[]                  = $filename;
    }
    $file_info = $this->store($file_info['content_opt'], null, $files, 'full_path_name_with_hash');

    // Replace the multiple calls with one call in the PHP code.
    $first = true;
    foreach ($group as $i)
    {
      if ($first)
      {
        $matches    = $calls[$i];
        $matches[5] = $file_info['path_name_in_sources_with_hash'];
        array_shift($matches);
        $lines[$i] = implode('', $matches);

        $first = false;
      }
      else
      {
        $lines[$i] = '';
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Helper function for {@link processPhpSourceFileReplaceMethodHelper}.
   *
   * @param string[]    $matches         The matches as returned by preg_match.
   * @param string      $optimizedMethod The appropriate optimized method.
   * @param string|null $className       The current class name of the PHP code.
   *
   * @return string
   */
  private function processPhpSourceFileReplaceMethodHelper(array $matches,
                                                           string $optimizedMethod,
                                                           ?string $className = null): string
  {
    if (isset($className))
    {
      $file_name = str_replace('\\', '/', $className).$this->extension;
    }
    else
    {
      $file_name = $matches[5];
    }

    if (substr($file_name, 0, 1)=='/')
    {
      $full_path = $this->parentResourceDirFullPath.'/'.$file_name;
    }
    else
    {
      $full_path = $this->resourceDirFullPath.'/'.$file_name;
    }

    if (!file_exists($full_path))
    {
      $this->logError("File '%s' not found", $full_path);
    }

    $real_path  = realpath($full_path);
    $matches[3] = $optimizedMethod;

    if (isset($className))
    {
      $matches[5] = "'".$this->getResourceInfo($real_path)['path_name_in_sources_with_hash']."'";
    }
    else
    {
      $matches[5] = $this->getResourceInfo($real_path)['path_name_in_sources_with_hash'];
    }

    array_shift($matches);

    return implode('', $matches);
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------