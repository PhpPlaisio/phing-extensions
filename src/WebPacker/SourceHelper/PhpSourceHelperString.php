<?php
declare(strict_types=1);

namespace Plaisio\Phing\Task\WebPacker\SourceHelper;

use Plaisio\Phing\Task\WebPacker\WebPackerInterface;
use Plaisio\Phing\Task\WebPacker\WebPackerTrait;
use Symfony\Component\Filesystem\Path;

/**
 * Helper class for replacing string references to resources with string references to the corresponding optimized
 * resource.
 */
class PhpSourceHelperString
{
  //--------------------------------------------------------------------------------------------------------------------
  use WebPackerTrait;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The methods of WebAssets class.
   *
   * @var string[]
   */
  private array $methods;

  /**
   * The regex for finding references to resources.
   *
   * @var string
   */
  private string $regex;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * PhpSourceHelperString constructor.
   *
   * @param WebPackerInterface $parent The parent object.
   */
  public function __construct(WebPackerInterface $parent)
  {
    $this->initWebPackerTrait($parent);

    $this->methods = array_merge(PhpSourceHelperCss::$methods, PhpSourceHelperJs::$methods);

    $this->regex = sprintf('/(?<quote1>[\'"])(?<uri>\/(%s)\/[a-zA-Z0-9_\-.\/]+)(?<quote2>[\'"])/',
                           implode('|', array_unique([preg_quote($this->cssDir),
                                                      preg_quote($this->jsDir),
                                                      preg_quote($this->imageDir)])));
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Analyzes a source for references to resources with string.
   *
   * @param array $source The details of the source.
   */
  public function analyzeHelper(array $source): void
  {
    $lines = explode(PHP_EOL, $source['src_content']);
    foreach ($lines as $i => $line)
    {
      if (!$this->callsWebAssetMethod($line))
      {
        if (preg_match_all($this->regex, $line, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL))
        {
          foreach ($matches as $match)
          {
            if ($match['quote1']===$match['quote2'])
            {
              $path = Path::join($this->parentResourcePath, $match['uri']);
              $this->task->logVerbose('    found %s (%s:%d)',
                                      Path::makeRelative($path, $this->buildPath),
                                      $match['uri'],
                                      $i + 1);

              $resource = $this->store->resourceSearchByPath($path);
              if ($resource===null)
              {
                $this->task->logError("Unable to find resource '%s' found at %s:%d",
                                      $match['uri'],
                                      $source['src_path'],
                                      $i + 1);
              }
              else
              {
                $this->store->insertRow('ABC_LINK1', ['src_id'      => $source['src_id'],
                                                      'rsr_id'      => $resource['rsr_id'],
                                                      'lk1_line'    => $i + 1,
                                                      'lk1_method'  => 'string',
                                                      'lk1_matches' => serialize($match)]);
              }
            }
          }
        }
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns true if and only if a line of code calls a WebAssets method.
   *
   * @param string $line The line.
   *
   * @return bool
   */
  private function callsWebAssetMethod(string $line): bool
  {
    foreach ($this->methods as $method)
    {
      if (strpos($line, $method)!==false) return true;
    }

    return false;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
