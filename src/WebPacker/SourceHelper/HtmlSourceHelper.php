<?php
declare(strict_types=1);

use Webmozart\PathUtil\Path;

/**
 * Helper class for html and xhtml files.
 */
class HtmlSourceHelper implements \SourceHelper, WebPackerInterface
{
  //--------------------------------------------------------------------------------------------------------------------
  use WebPackerTrait;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * PhpSourceHelperJs constructor.
   *
   * @param \WebPackerInterface $parent The parent object.
   */
  public function __construct(\WebPackerInterface $parent)
  {
    $this->initWebPackerTrait($parent);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public static function deriveType(string $content): bool
  {
    return (strpos(substr($content, 0, 120), '<!DOCTYPE html')!==false);
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
      if (preg_match('/\<(?<tag>link|img)(?<other1>[^\>]*)(?<attribute>href|src)=(?<quote1>[\'"])(?<uri>[a-zA-Z0-9_\-.\/]+)(?<quote2>[\'"])(?<other2>[^\>]*)\/\>/',
                     $line,
                     $matches,
                     PREG_UNMATCHED_AS_NULL))
      {
        if ($matches['quote1']===$matches['quote2'])
        {
          $path = $this->resolveFullPathOfResource($matches['uri'], $source['src_path']);
          $this->task->logVerbose('    found %s (%s:%d)',
                                  Path::makeRelative($path, $this->buildPath),
                                  $path,
                                  $i + 1);

          $resource = $this->store->resourceSearchByPath($path);
          if ($resource===null)
          {
            $this->task->logError("Unable to find resource '%s' found at %s:%d",
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
    $replace = [];
    foreach ($resources as $resource)
    {
      $matches = unserialize($resource['lk1_matches']);

      $replace[$matches[0]] = sprintf('<%s%s%s=%s%s%s%s/>',
                                      $matches['tag'],
                                      $matches['other1'],
                                      $matches['attribute'],
                                      $matches['quote1'],
                                      $resource['rsr_uri_optimized'],
                                      $matches['quote2'],
                                      $matches['other2']);
    }
    
    return strtr($source['src_content'], $replace);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Resolves a name of a resource to to full path of the resource on the file system.
   *
   * @param string $uri        The URI found in the html file.
   * @param string $sourcePath The full path of the html file.
   *
   * @return string
   */
  private function resolveFullPathOfResource(string $uri, string $sourcePath): string
  {
    if (substr($uri, 0, 1)==='/')
    {
      $fullPath = Path::join([$this->parentResourcePath, $uri]);
    }
    else
    {
      $dir      = Path::getDirectory($sourcePath);
      $fullPath = Path::join([$dir, $uri]);
    }

    return $fullPath;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
