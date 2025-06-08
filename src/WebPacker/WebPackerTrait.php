<?php
declare(strict_types=1);

namespace Plaisio\Phing\Task\WebPacker;

use Plaisio\Phing\Task\PlaisioTask;

trait WebPackerTrait
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * If set static Brotli compressed files of the optimized/minimized resources will be created.
   *
   * @var bool
   */
  public bool $brotliFlag = false;

  /**
   * Path to the Brotli program.
   *
   * @var string
   */
  public string $brotliPath = 'brotli';

  /**
   * The full path to the build dir.
   *
   * @var string
   */
  public string $buildPath;

  /**
   * The directory under $parentResourcePath for CSS files.
   *
   * @var string
   */
  public string $cssDir = 'css';

  /**
   * The extension CSS files.
   *
   * @var string
   */
  public string $cssExtension = 'css';

  /**
   * The command to minify CSS.
   *
   * @var string
   */
  public string $cssMinifyCommand = '/usr/bin/csso';

  /**
   * If set static gzipped files of the optimized/minimized resources will be created.
   *
   * @var bool
   */
  public bool $gzipFlag = false;

  /**
   * The directory under $parentResourcePath for images.
   *
   * @var string
   */
  public string $imageDir = 'images';

  /**
   * The command to run r.js.
   *
   * @var string
   */
  public string $jsCombineCommand = '/usr/bin/r.js';

  /**
   * The directory under $parentResourcePath for JS files.
   *
   * @var string
   */
  public string $jsDir = 'js';

  /**
   * The extension JS files.
   *
   * @var string
   */
  public string $jsExtension = 'js';

  /**
   * The command to minify JS.
   *
   * @var string
   */
  public string $jsMinifyCommand = '/usr/bin/uglifyjs -c -m';

  /**
   * The path to the node program.
   *
   * @var string
   */
  public string $jsNodePath = '/usr/bin/node';

  /**
   * The path to require.js relative to the parent resource path.
   *
   * @var string
   */
  public string $jsRequirePath = 'js/require.js';

  /**
   * The path to the parent resource dir (relative to the build dir).
   *
   * @var string
   */
  public string $parentResourceDir;

  /**
   * The full path to the parent resource dir.
   *
   * @var string
   */
  public string $parentResourcePath;

  /**
   * If set
   * <ul>
   * <li> The mtime of optimized/minimized resource files will be inherited from its original file.
   * <li> If two or more source files will be combined in a single resource file the mtime of this combined file will
   *      be set to the maximum mtime of the original resource files.
   * <li> When a PHP file is modified its mtime will be set to the maximum mtime of the PHP file and the referenced
   *      resource files.
   * </ul>
   *
   * @var bool
   */
  public bool $preserveModificationTime = false;

  /**
   * The ID of the fileset with resource files.
   *
   * @var string
   */
  public string $resourcesFilesetId;

  /**
   * The ID of the fileset with sources.
   *
   * @var string
   */
  public string $sourcesFilesetId;

  /**
   * @var ResourceStore
   */
  public ResourceStore $store;

  /**
   * The filename of the SQLite database, a.k.a. the store.
   *
   * @var string|null
   */
  public ?string $storeFilename = null;

  /**
   * The task.
   *
   * @var PlaisioTask
   */
  public PlaisioTask $task;

  /**
   * The list of the web asset classes, interfaces and traits.
   *
   * @var string[]
   */
  public array $webAssetsClasses = [];

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Initializes the properties of this trait.
   *
   * @param WebPackerInterface $parent
   */
  public function initWebPackerTrait(WebPackerInterface $parent): void
  {
    $this->brotliFlag               = $parent->brotliFlag;
    $this->brotliPath               = $parent->brotliPath;
    $this->buildPath                = $parent->buildPath;
    $this->cssDir                   = $parent->cssDir;
    $this->cssExtension             = $parent->cssExtension;
    $this->cssMinifyCommand         = $parent->cssMinifyCommand;
    $this->gzipFlag                 = $parent->gzipFlag;
    $this->imageDir                 = $parent->imageDir;
    $this->jsCombineCommand         = $parent->jsCombineCommand;
    $this->jsDir                    = $parent->jsDir;
    $this->jsExtension              = $parent->jsExtension;
    $this->jsMinifyCommand          = $parent->jsMinifyCommand;
    $this->jsNodePath               = $parent->jsNodePath;
    $this->jsRequirePath            = $parent->jsRequirePath;
    $this->parentResourceDir        = $parent->parentResourceDir;
    $this->parentResourcePath       = $parent->parentResourcePath;
    $this->preserveModificationTime = $parent->preserveModificationTime;
    $this->resourcesFilesetId       = $parent->resourcesFilesetId;
    $this->sourcesFilesetId         = $parent->sourcesFilesetId;
    $this->store                    = $parent->store;
    $this->storeFilename            = $parent->storeFilename;
    $this->task                     = $parent->task;
    $this->webAssetsClasses         = $parent->webAssetsClasses;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
