<?php
declare(strict_types=1);

use SetBased\Helper\ProgramExecution;
use Webmozart\PathUtil\Path;

/**
 * Task fo bundling and optimizing web assets.
 */
class WebPackerTask extends ResourceStoreTask
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Main method of this Phing task.
   */
  public function main()
  {
    $this->task = $this;

    $this->storeOpen();

    $this->prepareProjectData();
    $this->collectSourceFiles();
    $this->collectResourceFiles();
    $this->analyzeSourceFiles();
    $this->analyzeResourceFiles();
    $this->computeResourceDepth();
    $this->optimizeAllResources();
    $this->updateModifiedTime();
    $this->saveResources();
    $this->processSources();
    $this->unlinkResourceFiles();
    $this->unlinkUnusedResourceFiles();

    if ($this->gzipFlag) $this->gzipCompressOptimizedResourceFiles();
    if ($this->brotliFlag) $this->brotliCompressOptimizedResourceFiles();

    $this->storeClose();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Analyzes all resource files for references to resource files.
   */
  protected function analyzeResourceFiles(): void
  {
    $this->logInfo('Analyzing resource files for references to resource files');

    $resources = $this->store->resourceGetAll();
    foreach ($resources as $resource)
    {
      $this->logVerbose('    analyzing %s', Path::makeRelative($resource['rsr_path'], $this->buildPath));

      $class = $resource['rtp_class'];
      /** @var \ResourceHelper $helper */
      $helper = new $class($this);
      $helper->analyze($resource);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Computes the depth of the resources in the resources hierarchy.
   */
  protected function computeResourceDepth()
  {
    $helper = new JsMainResourceHelper($this);
    $helper->fixComputeResourceDepth();

    $depth = 0;
    do
    {
      $depth++;
      $n = $this->store->resourceUpdateDepth($depth);
    } while ($n!==0);

    $depth = 0;
    $n2    = 0;
    do
    {
      $depth++;
      $n1 = $n2;
      $n2 = $this->store->resourceUpdateDepthNotUsed();
    } while ($n2>$n1);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Removes resource files that have been optimized/minimized.
   */
  protected function unlinkResourceFiles(): void
  {
    $this->logInfo("Removing resource files");
    $count = 0;

    $resources = $this->store->resourceGetAllOptimized();
    foreach ($resources as $resource)
    {
      if (isset($resource['rsr_path']) && isset($resource['rsr_uri_optimized']))
      {
        // Resource file has an optimized/minimized version. Remove the original file.
        if (file_exists($resource['rsr_path']))
        {
          $this->logVerbose("  removing '%s'", Path::makeRelative($resource['rsr_path'], $this->buildPath));
          unlink($resource['rsr_path']);
          $count++;
        }
      }
    }

    $this->logInfo("  removed %d resource files", $count);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Removes resource files that are not referred by any PHP source or other resource file.
   */
  protected function unlinkUnusedResourceFiles(): void
  {
    $this->logInfo("Removing unused resource files");
    $count = 0;

    $resources = $this->store->resourceGetAllUnused();
    foreach ($resources as $resource)
    {
      $this->logInfo("  removing %s", Path::makeRelative($resource['rsr_path'], $this->buildPath));
      unlink($resource['rsr_path']);
      $count++;
    }

    $this->logInfo("  removed %d resource files", $count);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Analyzes all source files for references to resource files.
   */
  private function analyzeSourceFiles(): void
  {
    $this->logInfo('Analyzing source files for references to resource files');

    $sources = $this->store->sourceGetAll();
    foreach ($sources as $source)
    {
      $this->logVerbose('  analyzing %s', Path::makeRelative($source['src_path'], $this->buildPath));

      $class = $source['stp_class'];
      /** @var \SourceHelper $helper */
      $helper = new $class($this);
      $helper->analyze($source);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Compresses optimized/minimized resource files with Brotli.
   *
   * @throws \BuildException
   */
  private function brotliCompressOptimizedResourceFiles(): void
  {
    $this->logInfo('Brotli compressing files');

    $resources = $this->store->resourceGetAllToBeSaved();
    foreach ($resources as $resource)
    {
      $class = $resource['rtp_class'];
      if ($class::mustCompress())
      {
        $resourcePath = Path::join([$this->parentResourcePath, $resource['rsr_uri_optimized']]);
        $brotliPath   = sprintf('%s.br', $resourcePath);

        $this->logVerbose('  brotli compressing file %s to %s',
                          Path::makeRelative($resourcePath, $this->buildPath),
                          Path::makeRelative($brotliPath, $this->buildPath));

        $command = [$this->brotliPath, '--quality=11', '--keep', $resourcePath];
        $this->execCommand($command);

        if (filesize($resourcePath)<filesize($brotliPath))
        {
          $this->logVerbose('    compressed file larger than original file');

          unlink($brotliPath);
        }
        else
        {
          $this->setModificationTime($brotliPath, $resource['rsr_mtime']);
          $this->setFilePermissions($brotliPath, $resourcePath);
        }
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Get info about each file in the fileset.
   */
  private function collectResourceFiles(): void
  {
    $this->logInfo('Collecting resource files');

    $fileset   = $this->getProject()->getReference($this->resourcesFilesetId);
    $filenames = $fileset->getDirectoryScanner()->getIncludedFiles();
    $suc       = ksort($filenames);
    if ($suc===false) $this->logError('ksort failed');

    $resourceTypes = $this->store->resourceTypeGetAll();

    foreach ($filenames as $filename)
    {
      $path = $fileset->getDir().'/'.$filename;
      if (!is_link($path))
      {
        $path = realpath($path);

        $this->logVerbose('  collect %s', Path::makeRelative($path, $this->buildPath));

        $source = file_get_contents($path);
        if ($source===false) $this->logError("Unable to read file '%s'", $path);

        $rtpId = $this->deriveTypeOfResourceFile($resourceTypes, $source, $path);
        if ($rtpId===null)
        {
          $this->logInfo('  Unknown file type %s', Path::makeRelative($path, $this->buildPath));
        }
        else
        {

          $this->store->insertRow('ABC_RESOURCE', ['rsr_id'                => null,
                                                   'rtp_id'                => $rtpId,
                                                   'rsr_path'              => $path,
                                                   'rsr_mtime'             => filemtime($path),
                                                   'rsr_depth'             => null,
                                                   'rsr_content'           => $source,
                                                   'rsr_content_optimized' => null,
                                                   'rsr_uri_optimized'     => null]);
        }
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   *  Collects full path for each source file in the source fileset.
   */
  private function collectSourceFiles(): void
  {
    $this->logInfo('Collecting source files');

    $fileset   = $this->getProject()->getReference($this->sourcesFilesetId);
    $filenames = $fileset->getDirectoryScanner()->getIncludedFiles();
    $suc       = ksort($filenames);
    if ($suc===false) $this->logError('ksort failed');

    $sourceTypes = $this->store->sourceTypeGetAll();

    foreach ($filenames as $filename)
    {
      $path = $fileset->getDir().'/'.$filename;
      if (!is_link($path))
      {
        $path = realpath($path);

        $this->logVerbose('  collect %s', Path::makeRelative($path, $this->buildPath));

        $source = file_get_contents($path);
        if ($source===false) $this->logError("Unable to read file '%s'", $path);

        $stpId = $this->deriveTypeOfSourceFile($sourceTypes, $source, $path);
        if ($stpId===null)
        {
          $this->logInfo('  Unknown file type %s', Path::makeRelative($path, $this->buildPath));
        }
        else
        {
          $this->store->insertRow('ABC_SOURCE', ['src_id'      => null,
                                                 'stp_id'      => $stpId,
                                                 'src_path'    => $path,
                                                 'src_mtime'   => filemtime($filename),
                                                 'src_content' => $source]);
        }
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Derive the content type of the source file.
   *
   * @param array  $resourceTypes The details of all resource types.
   * @param string $content       The content of the resource file.
   * @param string $path          The path of the resource file.
   *
   * @return int|null
   */
  private function deriveTypeOfResourceFile(array $resourceTypes, string $content, string $path): ?int
  {
    $basename = basename($path);
    foreach ($resourceTypes as $sourceType)
    {
      if (preg_match($sourceType['rtp_regex'], $basename))
      {
        $class = $sourceType['rtp_class'];
        $match = $class::deriveType($content);

        if ($match===true)
        {
          return $sourceType['rtp_id'];
        }
      }
    }

    return null;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Derive the content type of the source file.
   *
   * @param array  $sourceTypes The details of all source types.
   * @param string $content     The content of the source file.
   * @param string $path        The path of the source file.
   *
   * @return int|null
   */
  private function deriveTypeOfSourceFile(array $sourceTypes, string $content, string $path): ?int
  {
    $basename = basename($path);
    foreach ($sourceTypes as $sourceType)
    {
      if (preg_match($sourceType['stp_regex'], $basename))
      {
        $class = $sourceType['stp_class'];
        $match = $class::deriveType($content);

        if ($match===true)
        {
          return $sourceType['stp_id'];
        }
      }
    }

    return null;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Executes an external program.
   *
   * @param string[] $command The command as array.
   *
   * @return string[] The output of the command.
   */
  private function execCommand(array $command): array
  {
    $this->task->logVerbose('Execute: %s', implode(' ', $command));
    [$output, $ret] = ProgramExecution::exec1($command, null);
    if ($ret!=0)
    {
      $this->task->logError("Error executing '%s':\n%s", implode(' ', $command), implode(PHP_EOL, $output));
    }
    else
    {
      foreach ($output as $line)
      {
        $this->task->logVerbose($line);
      }
    }

    return $output;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Compresses optimized/minimized resource files with gzip.
   *
   * @throws \BuildException
   */
  private function gzipCompressOptimizedResourceFiles(): void
  {
    $this->logInfo('Gzip compressing files');

    $resources = $this->store->resourceGetAllToBeSaved();
    foreach ($resources as $resource)
    {
      $class = $resource['rtp_class'];
      if ($class::mustCompress())
      {
        $resourcePath = Path::join([$this->parentResourcePath, $resource['rsr_uri_optimized']]);
        $gzipPath     = sprintf('%s.gz', $resourcePath);

        $this->logVerbose('  gzip compressing file %s to %s',
                          Path::makeRelative($resourcePath, $this->buildPath),
                          Path::makeRelative($gzipPath, $this->buildPath));

        $gzipData = gzencode($resource['rsr_content_optimized'], 9);
        if ($gzipData===false)
        {
          $this->logError('gzencode failed');
        }

        if (strlen($gzipData)<strlen($resource['rsr_content_optimized']))
        {
          $status = file_put_contents($gzipPath, $gzipData);
          if ($status===false)
          {
            $this->logError("Unable to write to file '%s'", $gzipPath);
          }

          $this->setModificationTime($gzipPath, $resource['rsr_mtime']);
          $this->setFilePermissions($gzipPath, $resourcePath);
        }
        else
        {
          $this->logVerbose('    compressed file larger than original file');
        }
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Optimizes all resources.
   */
  private function optimizeAllResources()
  {
    $this->logInfo('Optimizing all resources');

    $max = $this->store->resourceGetMaxDepth();
    for ($depth = $max; $depth>=1; $depth--)
    {
      $this->logVerbose('  optimizing resources at depth %d', $depth);

      $resources = $this->store->resourceGetAllByDepth($depth);
      foreach ($resources as $resource)
      {
        $this->logVerbose('    optimizing %s', Path::makeRelative($resource['rsr_path'], $this->buildPath));

        $class = $resource['rtp_class'];
        /** @var \ResourceHelper $helper */
        $helper = new $class($this);

        $resources        = $this->store->resourceGetAllReferredByReSource($resource['rsr_id']);
        $contentOptimized = $helper->optimize($resource, $resources);

        $this->store->resourceUpdateOptimized($resource['rsr_id'], $contentOptimized);
        $resource = $this->store->resourceGetById($resource['rsr_id']);
        $path     = $helper->uriOptimizedPath($resource);
        $this->store->resourceUpdateNameOptimized($resource['rsr_id'], $path);
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Process all source files referring the resources files.
   */
  private function processSources()
  {
    $this->logInfo('Processing PHP source files');

    $sources = $this->store->sourceGetAllWithReferences();
    foreach ($sources as $source)
    {
      $this->logVerbose('  processing %s', Path::makeRelative($source['src_path'], $this->buildPath));

      $class = $source['stp_class'];
      /** @var SourceHelper $helper */
      $helper = new $class($this);

      $resources  = $this->store->resourceGetAllReferredBySource($source['src_id']);
      $newContent = $helper->process($source, $resources);

      if ($newContent!==$source['src_content'])
      {
        // Write sources file with modified references to resource files.
        $status = file_put_contents($source['src_path'], $newContent);
        if ($status===false)
        {
          $this->logError("Updating file '%s' failed", $source['src_path']);
        }

        $this->logInfo('  updated file %s', Path::makeRelative($source['src_path'], $this->buildPath));
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Saves all optimized resources.
   */
  private function saveResources()
  {
    $this->logInfo('Saving all resources');

    $resources = $this->store->resourceGetAllToBeSaved();
    foreach ($resources as $resource)
    {
      $path = Path::join([$this->parentResourcePath, $resource['rsr_uri_optimized']]);
      $dir  = Path::getDirectory($path);

      $this->logVerbose('  writing %s', Path::makeRelative($path, $this->buildPath));

      if (!is_dir($dir))
      {
        mkdir($dir);
      }

      file_put_contents($path, $resource['rsr_content_optimized']);
      $this->setModificationTime($path, $resource['rsr_mtime']);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Closes the store.
   */
  private function storeClose(): void
  {
    $this->store->close();
    $this->storeUnlinkIfRequired(false);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Opens the store.
   */
  private function storeOpen(): void
  {
    $this->storeUnlinkIfRequired(true);
    $this->store = new ResourceStore($this->storeFilename, __DIR__.'/../lib/ddl/create_tables.sql');

    $this->store->insertRow('ABC_SOURCE_TYPE', ['stp_id'    => null,
                                                'stp_regex' => '/\.php$/i',
                                                'stp_name'  => 'php',
                                                'stp_class' => 'PhpSourceHelper']);

    $this->store->insertRow('ABC_SOURCE_TYPE', ['stp_id'    => null,
                                                'stp_regex' => '/\.(html|xhtml)$/i',
                                                'stp_name'  => 'html',
                                                'stp_class' => 'HtmlSourceHelper']);

    $this->store->insertRow('ABC_RESOURCE_TYPE', ['rtp_id'    => null,
                                                  'rtp_regex' => '/\.css$/i',
                                                  'rtp_name'  => 'css',
                                                  'rtp_class' => 'CssResourceHelper']);

    $this->store->insertRow('ABC_RESOURCE_TYPE', ['rtp_id'    => null,
                                                  'rtp_regex' => '/\.txt$/i',
                                                  'rtp_name'  => 'css-list',
                                                  'rtp_class' => 'CssListResourceHelper']);

    $this->store->insertRow('ABC_RESOURCE_TYPE', ['rtp_id'    => null,
                                                  'rtp_regex' => '/\.txt$/i',
                                                  'rtp_name'  => 'text',
                                                  'rtp_class' => 'TextResourceHelper']);

    $this->store->insertRow('ABC_RESOURCE_TYPE', ['rtp_id'    => null,
                                                  'rtp_regex' => '/\.main\.js$/i',
                                                  'rtp_name'  => 'js.main',
                                                  'rtp_class' => 'JsMainResourceHelper']);

    $this->store->insertRow('ABC_RESOURCE_TYPE', ['rtp_id'    => null,
                                                  'rtp_regex' => '/\.js$/i',
                                                  'rtp_name'  => 'js',
                                                  'rtp_class' => 'JsResourceHelper']);

    $this->store->insertRow('ABC_RESOURCE_TYPE', ['rtp_id'    => null,
                                                  'rtp_regex' => '/\.(png|jpg|jpeg|gif|webp)$/i',
                                                  'rtp_name'  => 'image',
                                                  'rtp_class' => 'ImageResourceHelper']);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Removes the store (the SQLite database) if required (i.e. the basename starts with a dot.)
   *
   * @param bool $force The store will be removed always if exists.
   */
  private function storeUnlinkIfRequired(bool $force): void
  {
    if ($this->storeFilename!==null)
    {
      $basename = Path::getFilename($this->storeFilename);
      if (($basename[0]==='.' || $force) && file_exists($this->storeFilename))
      {
        unlink($this->storeFilename);
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Computes the last modification time of each source and resource based on its own mtime and mtime of its dependents.
   */
  private function updateModifiedTime(): void
  {
    if ($this->preserveModificationTime)
    {
      $max = $this->store->resourceGetMaxDepth();
      for ($depth = $max - 1; $depth>=1; $depth--)
      {
        $resources = $this->store->resourceGetAllByDepth($depth);
        foreach ($resources as $resource)
        {
          $this->store->resourceUpdateMtime($resource['rsr_id']);
        }
      }

      $sources = $this->store->sourceGetAllWithReferences();
      foreach ($sources as $source)
      {
        $this->store->sourceUpdateMtime($source['src_id']);
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
