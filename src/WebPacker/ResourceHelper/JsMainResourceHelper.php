<?php
declare(strict_types=1);

use Webmozart\PathUtil\Path;

/**
 * Helper class for JS resources.
 */
class JsMainResourceHelper extends JsResourceHelper
{
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
    unset($resource);
    // Nothing to do.
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Computes the depth of the resources in the resources hierarchy.
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

    return sprintf('/%s/%s.%s', 'js', $md5, 'js');
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

    $resourcePath = Path::join([$this->parentResourcePath, 'js']);

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
    $path       = $this->parentResourcePath.'/'.$this->jsRequirePath;
    $require_js = file_get_contents($path);
    if ($code===false) $this->task->logError("Unable to read file '%s'", $path);

    // Combine require.js and all required includes.
    $code = $require_js.$code;

    // Remove temporary files.
    unlink($tmp_name2);
    unlink($tmp_name1);

    return ['code' => $code, 'parts' => $parts];
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

    preg_match('/^(.*requirejs.config)(.*}\))(.*)$/sm', $main, $matches);
    if (!isset($matches[2])) $this->task->logError("Unable to fine 'requirejs.config' in file '%s'", $filename);

    return $matches[2];
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

    $paths     = [];
    $resources = $this->store->resourceGetAllReferredByResource($resource['rsr_id']);
    foreach ($resources as $resource2)
    {
      $hash = Path::getFilenameWithoutExtension($resource2['rsr_uri_optimized']);
      if (isset($resource2['rsr_path']))
      {
        $module = $this->getNamespaceFromResourceFilename($resource2['rsr_path']);
        $char   = $module[0];
        if (strpos($module, '/')!==false && mb_strtoupper($char)===$char)
        {
          $paths[$hash] = $module;
        }
      }
    }

    // Convert the paths to proper JS code.
    $matches[2] = json_encode(array_flip($paths), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    array_shift($matches);

    return implode('', $matches);
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
