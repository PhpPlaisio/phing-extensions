<?php
declare(strict_types=1);

namespace Plaisio\Phing\Task\WebPacker\ResourceHelper;

use Webmozart\PathUtil\Path;

/**
 * Helper class for JS resources.
 */
class JsMainResourceHelper extends JsResourceHelper
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * If true, it is the first time that a *.main.js is been optimized.
   *
   * @var bool
   */
  private static bool $first = true;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public static function deriveType(string $content): bool
  {
    unset($content);

    return true;
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
  public function analyze(array $resource): void
  {
    $this->validatePaths($resource);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Computes the depth of the resources in the resource hierarchy.
   */
  public function fixComputeResourceDepth()
  {
    $rows = $this->store->resourceFixDepthForJs();
    foreach ($rows as $row)
    {
      $name = $this->getNamespaceFromResourceFilename($row['rsr_rsr_path']);
      $this->store->insertRow('ABC_LINK2', ['rsr_id_src'  => $row['src_rsr_id'],
                                            'rsr_id_rsr'  => $row['rsr_rsr_id'],
                                            'lk2_name'    => $name,
                                            'lk2_line'    => 999999,
                                            'lk2_matches' => null]);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public function optimize(array $resource, array $resources): string
  {
    $this->dumpAllJsSources();

    $combineInfo = $this->combine($resource['rsr_path']);
    $filesInfo   = $this->pathsWithHashedPaths($resource);
    $js          = $combineInfo['code'];
    $js          .= $filesInfo;

    return $this->minimizeJsCode($js);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public function uriOptimizedPath(array $resource): ?string
  {
    $md5 = md5($resource['rsr_content_optimized'] ?? '');

    return sprintf('/%s/%s.%s', $this->jsDir, $md5, $this->jsExtension);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Combines all JavaScript files required by a main JavaScript file.
   *
   * @param string $realPath The path to the main JavaScript file.
   *
   * @return array The combined code and parts.
   */
  private function combine(string $realPath): array
  {
    $config = $this->extractConfigFromMainFile($realPath);

    // Create temporary file with config.
    $tmp_name1 = tempnam('.', 'plaisio_');
    $handle    = fopen($tmp_name1, 'w');
    fwrite($handle, $config);
    fclose($handle);

    $resourcePath = Path::join([$this->parentResourcePath, $this->jsDir]);

    // Create temporary file for combined JavaScript code.
    $tmp_name2 = tempnam($resourcePath, 'plaisio_');

    // Run r.js.
    $command = [$this->jsCombineCommand,
                '-o',
                $tmp_name1,
                'baseUrl='.$resourcePath,
                'optimize=none',
                'name='.$this->getNamespaceFromResourceFilename($realPath),
                'out='.$tmp_name2];
    $output  = $this->execCommand($command);

    // Get all files of the combined code.
    $parts   = [];
    $trigger = array_search('----------------', $output);
    foreach ($output as $index => $file)
    {
      if ($index>$trigger && !empty($file))
      {
        $parts[] = $file;
      }
    }

    // Get the combined the JavaScript code.
    $code = file_get_contents($tmp_name2);
    if ($code===false) $this->task->logError("Unable to read file '%s'", $tmp_name2);

    // Get require.js
    $path      = $this->parentResourcePath.'/'.$this->jsRequirePath;
    $requireJs = file_get_contents($path);
    if ($requireJs===false) $this->task->logError("Unable to read file '%s'", $path);

    // Combine require.js and all required includes.
    $code = $requireJs.$code;

    // Remove temporary files.
    unlink($tmp_name2);
    unlink($tmp_name1);

    return ['code' => $code, 'parts' => $parts];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Replace all JS file with their optimized content. This required because the combine command read the JS file from
   * the filesystem and does some magic manipulation with the JS code.
   */
  private function dumpAllJsSources()
  {
    if (self::$first)
    {
      $resources = $this->store->resourceGetAllByType('js');
      foreach ($resources as $resource)
      {
        $ret = file_put_contents($resource['rsr_path'], $resource['rsr_content_optimized']);
        if ($ret===false)
        {
          $this->task->logError("Unable to write file '%s'", $resource['rsr_path']);
        }
      }

      self::$first = false;
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @param string $filename
   *
   * @return string
   */
  private function extractConfigFromMainFile(string $filename): string
  {
    $main = file_get_contents($filename);
    if ($main===false) $this->task->logError("Unable to read file '%s'", $filename);

    $config = null;
    $n      = preg_match('/requirejs\.config(.*)$/sm', $main, $matches1);
    if ($n===1)
    {
      $n = preg_match('/\((?:[^)(]+|(?R))*\)/sm', $matches1[1], $matches2);
      if ($n===1)
      {
        $config = rtrim(ltrim(trim($matches2[0]), '('), ')');
      }
    }

    if ($config===null)
    {
      $this->task->logError("Unable to find 'requirejs.config' in file '%s'", $filename);
    }

    return $config;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Reads the main.js file and returns baseUrl and paths.
   *
   * @param string $mainJsFile
   *
   * @return array
   */
  private function extractPaths(string $mainJsFile): array
  {
    $command = [$this->jsNodePath,
                __DIR__.'/../../../lib/extract_config.js',
                $mainJsFile];
    $output  = $this->execCommand($command);
    $config  = json_decode(implode(PHP_EOL, $output), true);

    return [$config['baseUrl'], $config['paths']];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Rewrites paths in requirejs.config. Adds path names from namespaces and aliases to filenames with hashes.
   *
   * @param array $resource The details of the JS file.
   *
   * @return string
   */
  private function pathsWithHashedPaths(array $resource): string
  {
    // Read the main file.
    $js = file_get_contents($resource['rsr_path']);
    if ($js===false) $this->task->logError("Unable to read file '%s'", $resource['rsr_path']);

    // Extract paths from main.
    preg_match('/^(.*paths:[^{]*)({[^}]*})(.*)$/sm', $js, $matches);
    if (!isset($matches[2])) $this->task->logError("Unable to find paths in '%s'", $resource['rsr_path']);

    $paths = [];
    [$baseUrl, $aliases] = $this->extractPaths($resource['rsr_path']);
    if ($baseUrl!==null && $paths!==null)
    {
      foreach ($aliases as $alias => $relPath)
      {
        $path      = sprintf('%s.%s',
                             Path::join($this->parentResourcePath, ltrim($baseUrl, '/'), $relPath),
                             $this->jsExtension);
        $resource2 = $this->store->resourceSearchByPath($path);
        if ($resource2!==null)
        {
          $paths[$alias] = Path::getFilenameWithoutExtension($resource2['rsr_uri_optimized']);
        }
        else
        {
          $paths[$alias] = $relPath;
        }
      }
    }

    $resources = $this->store->resourceGetAllReferredByResource($resource['rsr_id']);
    foreach ($resources as $resource2)
    {
      $hash = Path::getFilenameWithoutExtension($resource2['rsr_uri_optimized']);
      if (isset($resource2['rsr_path']))
      {
        $module = $this->getNamespaceFromResourceFilename($resource2['rsr_path']);
        if (strpos($module, '/')!==false)
        {
          $paths[$module] = $hash;
        }
      }
    }

    // Convert the paths to proper JS code.
    $matches[2] = json_encode($paths, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    array_shift($matches);

    return implode('', $matches);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Validates all paths (i.e. module name to dir/file) exists.
   *
   * @param array $resource The details of the main.js file.
   */
  private function validatePaths(array $resource): void
  {
    [$baseUrl, $paths] = $this->extractPaths($resource['rsr_path']);

    foreach ($paths as $name => $file)
    {
      $path = Path::join($this->parentResourcePath, $baseUrl, $file.'.'.$this->jsExtension);
      if (!file_exists($path))
      {
        $this->task->logError("Path '%s: %s' ('%s') in file '%s' does not exist",
                              $name,
                              $file,
                              Path::makeRelative($path, $this->buildPath),
                              Path::makeRelative($resource['rsr_path'], $this->buildPath));
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
