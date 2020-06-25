<?php
declare(strict_types=1);

trait WebPackerTrait
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * If set static Brotli compressed files of the optimized/minimized resources will be created.
   *
   * @var bool
   */
  public $brotliFlag = false;

  /**
   * Path to the Brotli program.
   *
   * @var string
   */
  public $brotliPath = 'brotli';

  /**
   * The full path to the build dir.
   *
   * @var string
   */
  public $buildPath;

  /**
   * The command to minify CSS.
   *
   * @var string
   */
  public $cssMinifyCommand = '/usr/bin/csso';

  /**
   * Enables/disables compression of CSS. This flag is for testing purposes only.
   *
   * @var bool
   */
  public $cssMinimize = true;

  /**
   * If set static gzipped files of the optimized/minimized resources will be created.
   *
   * @var bool
   */
  public $gzipFlag = false;

  /**
   * The command to run r.js.
   *
   * @var string
   */
  public $jsCombineCommand = '/usr/bin/r.js';

  /**
   * The command to minify JS.
   *
   * @var string
   */
  public $jsMinifyCommand = '/usr/bin/uglifyjs -c -m';

  /**
   * The path to the node program.
   *
   * @var string
   */
  public $jsNodePath = '/usr/bin/node';

  /**
   * The path to require.js relative to the parent resource path.
   *
   * @var string
   */
  public $jsRequirePath = 'js/require.js';

  /**
   * The path to the parent resource dir (relative to the build dir).
   *
   * @var string
   */
  public $parentResourceDir;

  /**
   * The full path to the parent resource dir.
   *
   * @var string
   */
  public $parentResourcePath;

  /**
   * If set
   * <ul>
   * <li> The mtime of optimized/minimized resource files will be inherited from its originals file.
   * <li> If two or more source files will be combined in a single resource file the mtime of this combined file will
   *      be set to the maximum mtime of the original resource files.
   * <li> When a PHP file is modified its mtime will be set to the maximum mtime of the PHP file and the referenced
   *      resource files.
   * </ul>
   *
   * @var bool
   */
  public $preserveModificationTime = false;

  /**
   * The ID of the fileset with resource files.
   *
   * @var string
   */
  public $resourcesFilesetId;

  /**
   * The ID of the fileset with sources.
   *
   * @var string
   */
  public $sourcesFilesetId;

  /**
   * @var ResourceStore
   */
  public $store;

  /**
   * The filename of the SQLite database, a.k.a. the store.
   *
   * @var string|null
   */
  public $storeFilename;

  /**
   * The task.
   *
   * @var \PlaisioTask
   */
  public $task;

  /**
   * The list of the web asset classes, interfaces and traits.
   *
   * @var
   */
  public $webAssetsClasses = [];

  //--------------------------------------------------------------------------------------------------------------------

  /**
   * Initializes the properties of this trait.
   *
   * @param WebPackerInterface $parent
   */
  public function initWebPackerTrait(\WebPackerInterface $parent)
  {
    $this->brotliFlag               = $parent->brotliFlag;
    $this->brotliPath               = $parent->brotliPath;
    $this->buildPath                = $parent->buildPath;
    $this->gzipFlag                 = $parent->gzipFlag;
    $this->parentResourceDir        = $parent->parentResourceDir;
    $this->parentResourcePath       = $parent->parentResourcePath;
    $this->preserveModificationTime = $parent->preserveModificationTime;
    $this->resourcesFilesetId       = $parent->resourcesFilesetId;
    $this->sourcesFilesetId         = $parent->sourcesFilesetId;
    $this->store                    = $parent->store;
    $this->storeFilename            = $parent->storeFilename;
    $this->webAssetsClasses         = $parent->webAssetsClasses;
    $this->jsMinifyCommand          = $parent->jsMinifyCommand;
    $this->jsNodePath               = $parent->jsNodePath;
    $this->jsRequirePath            = $parent->jsRequirePath;
    $this->jsCombineCommand         = $parent->jsCombineCommand;
    $this->cssMinifyCommand         = $parent->cssMinifyCommand;
    $this->task                     = $parent->task;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
