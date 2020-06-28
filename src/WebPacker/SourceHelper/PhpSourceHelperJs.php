<?php
declare(strict_types=1);

use Webmozart\PathUtil\Path;

/**
 * Helper class for replacing calls to methods of WebAssets for including JS with calls to the corresponding
 * optimized methods.
 */
class PhpSourceHelperJs
{
  //--------------------------------------------------------------------------------------------------------------------
  use WebPackerTrait;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * All methods for including JS sources.
   *
   * @var array
   */
  public static $methods = ['jsAdmSetMain',
                            'jsAdmSetPageSpecificMain',
                            'jsAdmOptimizedSetMain',
                            'jsAdmFunctionCall',
                            'jsAdmOptimizedFunctionCall'];

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * PhpSourceHelperJs constructor.
   *
   * @param WebPackerInterface $parent The parent object.
   */
  public function __construct(WebPackerInterface $parent)
  {
    $this->initWebPackerTrait($parent);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Analyzes a PHP source file for references to resources files. Found references are stored in the store.
   *
   * @param array  $source        The details of the PHP source file.
   * @param array  $lines         The source as lines.
   * @param string $qualifiedName The fully qualified class name of the class found int he PHP source file.
   * @param array  $imports       The imports (use) found in the PHP source file.
   * @param string $namespace     The namespace used in the PHP source file.
   */
  public function analyzePhpSourceFileHelper1(array $source,
                                              array $lines,
                                              string $qualifiedName,
                                              array $imports,
                                              string $namespace): void
  {

    $indent     = '(?<indent>.*)';
    $call       = '(?<call>((Nub::\$)|(\$this->))nub->assets->)';
    $method     = '(?<method>jsAdmFunctionCall|jsAdmSetMain|jsAdmSetPageSpecificMain)';
    $class      = '(\(\s*)(?<class>__CLASS__|__TRAIT__)';
    $path       = '(\((\s*[\'"])(?<path>[a-zA-Z0-9_\-.\/]+))([\'"])';
    $resolution = '(\(\s*)(?<resolution>[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)::class';
    $other      = '(?<other>(.*))';
    $regex      = '/^'.$indent.$call.$method.'('.$class.'|'.$path.'|'.$resolution.')'.$other.'$/';

    foreach ($lines as $i => $line)
    {
      if (preg_match($regex, $line, $matches, PREG_UNMATCHED_AS_NULL))
      {
        $this->analyzePhpSourceFileHelper2($matches, $qualifiedName, $imports, $namespace, $source, $i + 1);
      }
      else
      {
        // Test for invalid usage of methods for including JS files.
        foreach (self::$methods as $method)
        {
          if (preg_match("/(->|::)($method)(\\()/", $line))
          {
            $this->task->logError("Unexpected usage of method '%s' at line %s:%d", $method, $source['src_path'], $i + 1);
          }
        }
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Analyzes a line in a PHP source file that calls a WebAssets method.
   *
   * @param array  $matches       The matches as returned by preg_match().
   * @param string $qualifiedName The fully qualified class name of the class found int he PHP source file.
   * @param array  $imports       The imports (use) found in the PHP source file.
   * @param string $namespace     The namespace used in the PHP source file.
   * @param array  $source        The details of the PHP source file.
   * @param int    $lineno        The line number in the PHP source file were the WebAssets method is called.
   */
  private function analyzePhpSourceFileHelper2(array $matches,
                                               string $qualifiedName,
                                               array $imports,
                                               string $namespace,
                                               array $source,
                                               int $lineno): void
  {
    [$path, $expression] = $this->deriveResourcePath($matches, $qualifiedName, $imports, $namespace);

    $this->task->logVerbose('    found %s (%s:%d)', Path::makeRelative($path, $this->buildPath), $expression, $lineno);

    $resource = $this->store->resourceSearchByPath($path);
    if ($resource===null)
    {
      $this->task->logError("Unable to find resource '%s' found at %s:%d", $path, $source['src_path'], $lineno);

      return;
    }

    $this->store->insertRow('ABC_LINK1', ['src_id'      => $source['src_id'],
                                          'rsr_id'      => $resource['rsr_id'],
                                          'lk1_line'    => $lineno,
                                          'lk1_method'  => $matches['method'],
                                          'lk1_matches' => serialize($matches)]);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Derives the full path of the included resource and the expression the refer to the resource.
   *
   * @param array  $matches       The matches from the regexp.
   * @param string $qualifiedName The fully qualified name of the class in the source file.
   * @param array  $imports       The imports in the source file.
   * @param string $namespace     The namespace of the sources file.
   *
   * @return array
   */
  private function deriveResourcePath(array $matches,
                                      string $qualifiedName,
                                      array $imports,
                                      string $namespace): array
  {
    switch (true)
    {
      case $matches['class']!==null:
        $filename   = sprintf('%s.%s', str_replace('\\', '/', $qualifiedName), $this->jsExtension);
        $expression = $matches['class'];
        break;

      case $matches['path']!==null:
        $filename   = sprintf('%s.%s', $matches['path'], $this->jsExtension);
        $expression = $matches['path'];
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
        $filename   = sprintf('%s.%s', str_replace('\\', '/', $tmp), $this->jsExtension);
        $expression = $matches['resolution'].'::class';
        break;

      default:
        throw new LogicException('Regex not correct');
    }

    if (in_array($matches['method'], ['jsAdmSetMain', 'jsAdmSetPageSpecificMain']))
    {
      $extension = Path::getExtension($filename);
      $filename  = Path::changeExtension($filename, '.main.'.$extension);
    }

    $path = $this->resolveFullPathOfResource($filename);

    return [$path, $expression];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Resolves a name of a resource to to full path of the resource on the file system.
   *
   * @param string $resourceName The name of the resource as found in the source file.
   *
   * @return string
   */
  private function resolveFullPathOfResource(string $resourceName): string
  {
    if (substr($resourceName, 0, 1)==='/')
    {
      $fullPath = Path::join([$this->parentResourcePath, $resourceName]);
    }
    else
    {
      $fullPath = Path::join([$this->parentResourcePath, $this->jsDir, $resourceName]);
    }

    return $fullPath;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
