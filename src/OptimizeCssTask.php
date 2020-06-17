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
    // If $fullPathName is not set $resource is concatenation of 2 or more optimized CSS file. There is no need to
    // convert relative paths and minimized $resource again. Moreover, it is not possible to convert relative paths
    // since $resource can be a concatenation of CSS files from different subdirectories.
    if ($fullPathName!==null)
    {
      $css = $this->convertRelativePaths($resource, $fullPathName);

      [$std_out, $std_err] = $this->runProcess($this->minifyCommand, $css);

      if ($std_err!=='') $this->logError($std_err);

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
      if (preg_match('/^(?<indent>.*)(?<call>((Nub::\$)|(\$this->))nub->assets->)(?<method>cssOptimizedAppendSource)(\(\s*[\'"])(?<path>[a-zA-Z0-9_\-.\/]+)([\'"]\s*\)\s*;)(.*)$/',
                     $line,
                     $matches))
      {
        $calls[$i] = $matches;

        if ($previous + 1!=$i)
        {
          if (!empty($group))
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
    $lines = explode(PHP_EOL, $phpCode);

    $class     = $this->extractClassname($lines);
    $namespace = $this->extractNamespace($lines);
    $imports   = $this->extractImports($lines);

    // Don't process files with class of namespace.
    if ($class===null || $namespace===null) return $phpCode;

    $qualifiedName = $namespace.'\\'.$class;

    // Don't process the class that defines the jsAdm* methods.
    if (in_array($qualifiedName, $this->webAssetsClasses)) return $phpCode;

    $indent     = '(?<indent>.*)';
    $call       = '(?<call>((Nub::\$)|(\$this->))nub->assets->)';
    $method     = '(?<method>cssAppendSource|cssAppendClassSpecificSource)';
    $class      = '(\(\s*)(?<class>__CLASS__|__TRAIT__)';
    $path       = '(\((\s*[\'"])(?<path>[a-zA-Z0-9_\-.\/]+))([\'"])';
    $resolution = '(\(\s*)(?<resolution>[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)::class';
    $other      = '(?<other>(.*))';
    $regex      = '/^'.$indent.$call.$method.'('.$class.'|'.$path.'|'.$resolution.')'.$other.'$/';

    foreach ($lines as $i => $line)
    {
      if (preg_match($regex, $line, $matches, PREG_UNMATCHED_AS_NULL))
      {
        $lines[$i] = $this->processPhpSourceFileReplaceMethodHelper($qualifiedName, $namespace, $imports, $matches);
      }
      else
      {
        // Test for invalid usage of methods for including CSS files.
        foreach ($this->methods as $method)
        {
          if (preg_match("/(->|::)($method)(\\()/", $line))
          {
            $this->logError("Unexpected usage of method '%s' at line %s:%d", $method, $filename, $i + 1);
          }
        }
      }
    }

    return implode(PHP_EOL, $lines);
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
    $files                   = [];
    $fileInfo                = [];
    $fileInfo['content_opt'] = '';
    foreach ($group as $i)
    {
      $filename = $this->parentResourceDirFullPath.$calls[$i]['path'];

      $this->logVerbose('Combining %s', $filename);

      $info = $this->getResourceInfoByHash($filename);
      $code = $info['content_opt'];

      $fileInfo['content_opt'] .= $code;
      $files[]                 = $filename;
    }
    $fileInfo = $this->store($fileInfo['content_opt'], null, $files, 'full_path_name_with_hash');

    // Replace the multiple calls with one call in the PHP code.
    $first = true;
    foreach ($group as $i)
    {
      if ($first)
      {
        $lines[$i] = sprintf("%s%s%s('%s');",
                             $calls[$i]['indent'],
                             $calls[$i]['call'],
                             $calls[$i]['method'],
                             $fileInfo['path_name_in_sources_with_hash']);

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
   * @param string $qualifiedName The fully qualified name of the class/trait/interface found in the source file.
   * @param string $namespace     The namespace found in the source file.
   * @param array  $imports       The imports found in the source file.
   * @param array  $matches       The matches of the regex.
   *
   * @return string
   */
  private function processPhpSourceFileReplaceMethodHelper(string $qualifiedName,
                                                           string $namespace,
                                                           array $imports,
                                                           array $matches): string
  {
    switch (true)
    {
      case $matches['class']!==null:
        $filename = str_replace('\\', '/', $qualifiedName).$this->extension;
        break;

      case $matches['path']!==null:
        $filename = $matches['path'];
        break;

      case $matches['resolution']!==null:
        if (isset($imports[$matches['resolution']]))
        {
          $tmp = $imports[$matches['resolution']];
        }
        else
        {
          $tmp = $namespace.'\\'.$matches['resolution'];
        }
        $filename = str_replace('\\', '/', $tmp).$this->extension;
        break;

      default:
        throw new LogicException('Regex not correct');
    }

    if (substr($filename, 0, 1)=='/')
    {
      $fullPath = $this->parentResourceDirFullPath.'/'.$filename;
    }
    else
    {
      $fullPath = $this->resourceDirFullPath.'/'.$filename;
    }

    if (!file_exists($fullPath))
    {
      $this->logError("File '%s' not found", $fullPath);
    }

    $realpath     = realpath($fullPath);
    $pathWithHash = $this->getResourceInfo($realpath)['path_name_in_sources_with_hash'];

    return sprintf("%s%s%s('%s');",
                   $matches['indent'],
                   $matches['call'],
                   'cssOptimizedAppendSource',
                   addslashes($pathWithHash));
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
