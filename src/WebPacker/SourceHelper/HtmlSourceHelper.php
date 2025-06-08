<?php
declare(strict_types=1);

namespace Plaisio\Phing\Task\WebPacker\SourceHelper;

use Plaisio\Phing\Task\WebPacker\WebPackerInterface;
use Plaisio\Phing\Task\WebPacker\WebPackerTrait;
use Symfony\Component\Filesystem\Path;

/**
 * Helper class for HTML and xHTML files.
 */
class HtmlSourceHelper implements SourceHelper, WebPackerInterface
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
    return (str_contains(substr($content, 0, 120), '<!DOCTYPE html'));
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public function analyze(array $source): void
  {
    $lines = explode(PHP_EOL, $source['src_content']);
    foreach ($lines as $i => $line)
    {
      if (preg_match('/^(?<before>.*)\<(?<tag>link|img|script)(?<other1>[^\>]*)(?<attribute>href|src)=(?<quote1>[\'"])(?<uri>[a-zA-Z0-9_\-.\/]+)(?<quote2>[\'"])(?<other2>[^\>]*)(?<after>.*)$/i',
                     $line,
                     $matches,
                     PREG_UNMATCHED_AS_NULL))
      {
        if ($matches['quote1']===$matches['quote2'])
        {
          $path = $this->resolveFullPathOfResource($matches['uri'], $source['src_path']);
          $this->task->logVerbose('    found %s (%s:%d).',
                                  Path::makeRelative($path, $this->buildPath),
                                  $matches['uri'],
                                  $i + 1);

          $resource = $this->store->resourceSearchByPath($path);
          if ($resource===null)
          {
            $this->task->logError("Unable to find resource '%s' found at %s:%d.",
                                  $matches['uri'],
                                  $source['src_path'],
                                  $i + 1);
          }
          else
          {
            $this->store->insertRow('ABC_LINK1', ['src_id'      => $source['src_id'],
                                                  'rsr_id'      => $resource['rsr_id'],
                                                  'lk1_line'    => $i + 1,
                                                  'lk1_method'  => 'link',
                                                  'lk1_matches' => serialize($matches)]);
          }
        }
      }
    }
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

      $lines[$resource['lk1_line'] - 1] = sprintf('%s<%s%s%s=%s%s%s%s%s',
                                                  $matches['before'],
                                                  $matches['tag'],
                                                  $matches['other1'],
                                                  $matches['attribute'],
                                                  $matches['quote1'],
                                                  $resource['rsr_uri_optimized'],
                                                  $matches['quote2'],
                                                  $matches['other2'],
                                                  $matches['after']);
    }

    return implode(PHP_EOL, $lines);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Resolves a name of a resource to full path of the resource on the file system.
   *
   * @param string $uri        The URI found in the html file.
   * @param string $sourcePath The full path of the html file.
   *
   * @return string
   */
  private function resolveFullPathOfResource(string $uri, string $sourcePath): string
  {
    if (str_starts_with($uri, '/'))
    {
      $fullPath = Path::join($this->parentResourcePath, $uri);
    }
    else
    {
      $dir      = Path::getDirectory($sourcePath);
      $fullPath = Path::join($dir, $uri);
    }

    return $fullPath;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
