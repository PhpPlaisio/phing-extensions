<?php
declare(strict_types=1);

namespace Plaisio\Phing\Task\WebPacker\ResourceHelper;

use Plaisio\Phing\Task\WebPacker\WebPackerInterface;
use Plaisio\Phing\Task\WebPacker\WebPackerTrait;
use Symfony\Component\Filesystem\Path;

/**
 * Helper class for CSS-list resources.
 */
class CssListResourceHelper implements ResourceHelper, WebPackerInterface
{
  //--------------------------------------------------------------------------------------------------------------------
  use WebPackerTrait;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
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
    $lines = explode(PHP_EOL, $content, 1);

    return (isset($lines[0]) && str_contains($lines[0], 'plaisio-css-list'));
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public static function mustCompress(): bool
  {
    return true;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public function analyze(array $resource1): void
  {
    $lines = explode(PHP_EOL, $resource1['rsr_content']);
    foreach ($lines as $i => $line)
    {
      $line = trim($line);
      if ($line!=='' && $line[0]!=='#')
      {
        $resourcePath = $this->cssResolveReferredResourcePath($line, $resource1['rsr_path']);
        $this->task->logVerbose('      found %s (%s).', Path::makeRelative($resourcePath, $this->buildPath), $line);

        $resource2 = $this->store->resourceSearchByPath($resourcePath);
        if ($resource2===null)
        {
          $this->task->logError("Unable to find resource '%s' found at %s:%d.",
                                $resourcePath,
                                $resource1['rsr_path'],
                                $i + 1);
        }
        else
        {
          $this->store->insertRow('ABC_LINK2', ['rsr_id_src'  => $resource1['rsr_id'],
                                                'rsr_id_rsr'  => $resource2['rsr_id'],
                                                'lk2_name'    => $line,
                                                'lk2_line'    => $i + 1,
                                                'lk2_matches' => null]);
        }
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public function optimize(array $resource, array $resources): ?string
  {
    $css = '';
    foreach ($resources as $resource)
    {
      $css .= $resource['rsr_content_optimized'];
    }

    return $css;
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
   * Returns the full path of a resource found in another resource.
   *
   * @param string $filename     The relative path found in the referring resource.
   * @param string $referrerPath The full path of the other resource referring to the resource.
   *
   * @return string
   */
  private function cssResolveReferredResourcePath(string $filename, string $referrerPath): string
  {
    if ($filename[0]==='/')
    {
      $resourcePath = Path::join($this->parentResourcePath, $filename);
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
