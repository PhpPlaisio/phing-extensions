<?php
declare(strict_types=1);

namespace Plaisio\Phing\Task\WebPacker\SourceHelper;

use Plaisio\Phing\Task\WebPacker\WebPackerInterface;
use Plaisio\Phing\Task\WebPacker\WebPackerTrait;
use SetBased\Exception\FallenException;
use Webmozart\PathUtil\Path;

/**
 * Replace references to resources with the references to the corresponding optimized resources in PHP code.
 */
class PhpSourceHelper implements SourceHelper, WebPackerInterface
{
  //--------------------------------------------------------------------------------------------------------------------
  use WebPackerTrait;

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
   * @inheritDoc
   */
  public static function deriveType(string $content): bool
  {
    return (strncmp($content, '<?php', 5)===0);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public function analyze(array $source): void
  {
    // If true the PHP code includes CSS or JS files.
    $includes = false;
    foreach (array_merge(PhpSourceHelperCss::$methods, PhpSourceHelperJs::$methods) as $method)
    {
      if (stripos($source['src_content'], $method)!==false)
      {
        $includes = true;
        break;
      }
    }

    if ($includes)
    {
      $this->analyzeWebAssets($source);
    }
    $this->analyzeString($source);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public function process(array $source, array $resources): ?string
  {
    $lines = explode(PHP_EOL, $source['src_content']);

    foreach ($resources as $resource)
    {
      $matches = unserialize($resource['lk1_matches']);
      switch ($resource['lk1_method'])
      {
        case 'cssAppendSource':
        case 'cssAppendSourcesList':
          $line = $this->replaceWebAssetMethod($matches, 'cssOptimizedAppendSource', $resource['rsr_uri_optimized']);
          break;

        case 'cssPushSource':
        case 'cssPushSourcesList':
          $line = $this->replaceWebAssetMethod($matches, 'cssOptimizedPushSource', $resource['rsr_uri_optimized']);
          break;

        case 'jsAdmSetMain':
          $line = $this->replaceWebAssetMethod($matches, 'jsAdmOptimizedSetMain', $resource['rsr_uri_optimized']);
          break;

        case 'jsAdmFunctionCall':
          $arg  = $this->getNamespaceFromResourceFilename($resource['rsr_path']);
          $line = $this->replaceWebAssetMethod($matches, 'jsAdmOptimizedFunctionCall', $arg);
          break;

        case 'string':
          $line = $this->replaceString($lines[$resource['lk1_line'] - 1], $matches, $resource['rsr_uri_optimized']);
          break;

        default:
          throw new FallenException('lk1_method', $resource['lk1_method']);
      }

      $lines[$resource['lk1_line'] - 1] = $line;
    }

    return implode(PHP_EOL, $lines);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Analyzes references to resource files via strings.
   *
   * @param array $source The details of the source.
   */
  private function analyzeString(array $source)
  {
    $lines = explode(PHP_EOL, $source['src_content']);

    $class     = $this->extractClassname($lines);
    $namespace = $this->extractNamespace($lines);

    if ($class!==null && $namespace!==null)
    {
      $qualifiedName = $namespace.'\\'.$class;

      // Don't process the WebAssets classes.
      if (in_array($qualifiedName, $this->webAssetsClasses)) return;
    }

    $helper = new PhpSourceHelperString($this);
    $helper->analyzeHelper($source);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Analyzes references to resource files via $this->nub->assets methods.
   *
   * @param array $source The details of the source.
   */
  private function analyzeWebAssets(array $source)
  {
    $lines = explode(PHP_EOL, $source['src_content']);

    $class     = $this->extractClassname($lines);
    $namespace = $this->extractNamespace($lines);
    $imports   = $this->extractImports($lines);

    // Don't process files with class of namespace.
    if ($class===null || $namespace===null) return;

    $qualifiedName = $namespace.'\\'.$class;

    // Don't process the WebAssets classes.
    if (in_array($qualifiedName, $this->webAssetsClasses)) return;

    $helper1 = new PhpSourceHelperCss($this);
    $helper1->analyzePhpSourceFileHelper1($source, $lines, $qualifiedName, $imports, $namespace);

    $helper2 = new PhpSourceHelperJs($this);
    $helper2->analyzePhpSourceFileHelper1($source, $lines, $qualifiedName, $imports, $namespace);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Extracts the name of the class, trait or interface from the PHP source code.
   *
   * @param array $lines The PHP source code.
   *
   * @return string|null
   */
  private function extractClassname(array $lines): ?string
  {
    $class = null;
    foreach ($lines as $i => $line)
    {
      if (preg_match('/^((abstract|final)\s+)?(class|trait|interface)\s+(?<class>[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/',
                     trim($line),
                     $matches))
      {
        if ($class===null)
        {
          $class = $matches['class'];
        }
        else
        {
          $this->task->logError("Found multiple classes, traits, or interfaces at line %d.", $i + 1);
        }
      }
    }

    return $class;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Extracts the imports from the PHP source code.
   *
   * @param array $lines The PHP source code.
   *
   * @return array
   */
  private function extractImports(array $lines): array
  {
    $imports = [];
    foreach ($lines as $i => $line)
    {
      if (preg_match('/^use\s+(?<class>[^ ]+)(\s+as\s+(?<alias>[^ ]+))?;$/',
                     trim($line),
                     $matches,
                     PREG_UNMATCHED_AS_NULL))
      {
        if (isset($matches['alias']))
        {
          $alias = $matches['alias'];
        }
        else
        {
          $parts = explode('\\', $matches['class']);
          $alias = end($parts);
        }

        $imports[$alias] = $matches['class'];
      }
    }

    return $imports;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Extracts the namespace from the PHP source code.
   *
   * @param array $lines The PHP source code.
   *
   * @return string|null
   */
  private function extractNamespace(array $lines): ?string
  {
    $namespace = null;
    foreach ($lines as $i => $line)
    {
      if (preg_match('/^namespace\s+(?<namespace>.+);$/', trim($line), $matches))
      {
        if ($namespace===null)
        {
          $namespace = $matches['namespace'];
        }
        else
        {
          $this->task->logError("Found multiple namespaces at line %d.", $i + 1);
        }
      }
    }

    return $namespace;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the namespace based on the name of a JavaScript file.
   *
   * @param string $resourceFilename The name of the JavaScript file.
   *
   * @return string
   */
  private function getNamespaceFromResourceFilename(string $resourceFilename): string
  {
    $name = Path::makeRelative($resourceFilename, Path::join([$this->parentResourcePath, $this->jsDir]));
    $dir  = Path::getDirectory($name);
    $name = Path::getFilenameWithoutExtension($name);
    $name = Path::getFilenameWithoutExtension($name);

    return Path::join([$dir, $name]);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Replaces a string reference to a resource with a string reference to the optimized source.
   *
   * @param string $line    The source line.
   * @param array  $matches The matches from the regexp.
   * @param string $uri     The URi of the optimized resource.
   *
   * @return string
   */
  private function replaceString(string $line, array $matches, string $uri): string
  {
    $search  = sprintf('%s%s%s', $matches['quote1'], $matches['uri'], $matches['quote2']);
    $replace = sprintf('%s%s%s', $matches['quote1'], $uri, $matches['quote2']);

    return str_replace($search, $replace, $line);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Replaces a call to a WebAsset method with a call to the corresponding optimized method.
   *
   * @param array  $matches The matches from the regexp.
   * @param string $method  The optimized method.
   * @param string $arg     The argument for the optimized method.
   *
   * @return string
   */
  private function replaceWebAssetMethod(array $matches, string $method, string $arg): string
  {
    return sprintf("%s%s%s('%s'%s",
                   $matches['indent'],
                   $matches['call'],
                   $method,
                   addslashes($arg),
                   $matches['other']);
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
