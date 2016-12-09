<?php
//----------------------------------------------------------------------------------------------------------------------

//----------------------------------------------------------------------------------------------------------------------
/**
 * Get images and create one big sprite and css in result.
 */
class SpriteTask extends \Task
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Directory with source images.
   *
   * @var string
   */
  private $basedir = '';

  /**
   * Directory for resulting css.
   *
   * @var string
   */
  private $css = '';

  /**
   * Image extension.
   *
   * @var string
   */
  private $extension = '';

  /**
   * If set stop build on errors.
   *
   * @var bool
   */
  private $haltOnError = true;

  /**
   * File name for result image.
   *
   * @var string
   */
  private $image = '';

  /**
   * Image height.
   *
   * @var int
   */
  private $imageHeight;

  /**
   * Image width.
   *
   * @var int
   */
  private $imageWidth;

  /**
   * Directory with images for concatenating.
   *
   * @var string
   */
  private $images = '';

  /**
   * Resource directory.
   *
   * @var string
   */
  private $resourceRoot = '';

  //--------------------------------------------------------------------------------------------------------------------
  /**
   *  Called by the project to let the task do it's work. This method may be
   *  called more than once, if the task is invoked more than once. For
   *  example, if target1 and target2 both depend on target3, then running
   *  <em>phing target1 target2</em> will run all tasks in target3 twice.
   *
   *  Should throw a BuildException if someting goes wrong with the build
   *
   *  This is here. Must be overloaded by real tasks.
   */
  public function main()
  {
    $images = $this->getImages();

    $cellCount = $this->calculateCellCount($images);
    $crc32     = $this->createSprite($images, $cellCount);
    $this->createCss($images, $crc32, $cellCount);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for basedir path.
   *
   * @param string $basedir
   */
  public function setBasedir($basedir)
  {
    $this->basedir = $basedir;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for css path.
   *
   * @param string $css
   */
  public function setCss($css)
  {
    $this->css = $css;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for result image path.
   *
   * @param string $image
   */
  public function setImage($image)
  {
    $this->image = $image;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for images path.
   *
   * @param string $images
   */
  public function setImages($images)
  {
    $this->images = $images;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for resourceroot.
   *
   * @param mixed $resourceRoot
   */
  public function setResourceRoot($resourceRoot)
  {
    $this->resourceRoot = $resourceRoot;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Prints an error message and depending on HaltOnError throws an exception.
   *
   * @param mixed ...$param The arguments as for [sprintf](http://php.net/manual/function.sprintf.php)
   *
   * @throws \BuildException
   */
  protected function logError()
  {
    $args   = func_get_args();
    $format = array_shift($args);

    foreach ($args as &$arg)
    {
      if (!is_scalar($arg)) $arg = var_export($arg, true);
    }

    if ($this->haltOnError) throw new \BuildException(vsprintf($format, $args));

    if (sizeof($args)==0)
    {
      $this->log($format, \Project::MSG_ERR);
    }
    else
    {
      $this->log(vsprintf($format, $args), \Project::MSG_ERR);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Calculate cells count for future square sprite.
   *
   * @param array $imgArray Array with paths for images.
   *
   * @return int
   */
  private function calculateCellCount($imgArray)
  {
    $cellCount = 0;
    for ($c = 1; ; $c++)
    {
      if ($c * $c>=count($imgArray))
      {
        $cellCount = $c;
        break;
      }
    }

    return $cellCount;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Check sizes.
   *
   * @param array $images Images.
   */
  private function checkSizes($images)
  {
    foreach ($images as $image)
    {
      $data   = getimagesize($image);
      $width  = $data[0];
      $height = $data[1];
      if (!$this->imageHeight)
      {
        $this->imageHeight = $height;
      }
      if (!$this->imageWidth)
      {
        $this->imageWidth = $width;
      }
      if ($width!=$this->imageWidth || $this->imageHeight!=$height)
      {
        $this->log('Images have different sizes.', Project::MSG_ERR);
        exit(0);
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Create css file for work with sprite.
   *
   * @param array  $imgArray  Array with paths for images.
   * @param string $crc32     Hash from new sprite.
   * @param  int   $cellCount Cells count for square sprite.
   */
  private function createCss($imgArray, $crc32, $cellCount)
  {
    $cssStyles = '';
    foreach ($imgArray as $file)
    {
      if ($cssStyles) $cssStyles .= ',';
      $cssStyles .= '.my-icons-'.basename($file, '.'.pathinfo($file, PATHINFO_EXTENSION));
    }
    $cssStyles .= '{ background-image: url('.$this->basedir.'/'.basename($this->image, ',png').'-'.$crc32.'.png) no-repeat; }';
    $yi = 0;
    $xi = 0;
    foreach ($imgArray as $file)
    {
      if ($xi==$cellCount)
      {
        $xi = 0;
        $yi++;
      }
      $cssStyles .= '.my-icons-'.basename($file, '.'.pathinfo($file, PATHINFO_EXTENSION)).' { background-position: -'.($this->imageWidth * $xi).'px -'.($this->imageHeight * $yi).'px; }';
      $xi++;
    }
    $fp = fopen($this->resourceRoot.'/'.$this->css, 'w');
    fwrite($fp, $cssStyles);
    fclose($fp);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Create one square sprite from images.
   *
   * @param array $imgArray  Array with paths for images.
   * @param  int  $cellCount Cells count for square sprite.
   *
   * @return int
   */
  private function createSprite($imgArray, $cellCount)
  {
    $im = imagecreatetruecolor($this->imageWidth * $cellCount, $this->imageHeight * $cellCount);

    // Add alpha channel to image (transparency)
    imagesavealpha($im, true);
    $alpha = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagefill($im, 0, 0, $alpha);

    // Append images to sprite and generate CSS lines
    $yi = 0;
    $xi = 0;
    foreach ($imgArray as $file)
    {
      $im2 = imagecreatefrompng($file);
      if ($xi==$cellCount)
      {
        $xi = 0;
        $yi++;
      }
      imagecopy($im, $im2, ($this->imageWidth * $xi), ($this->imageHeight * $yi), 0, 0, $this->imageWidth, $this->imageHeight);
      $xi++;
    }
    // Save image to file
    $imagePath = $this->resourceRoot.'/'.$this->image;
    imagepng($im, $imagePath);
    $crc32 = crc32(file_get_contents($imagePath));
    unlink($imagePath);
    imagepng($im, $this->resourceRoot.'/'.pathinfo($this->image, PATHINFO_DIRNAME).'/'.basename($this->image, '.png').'-'.$crc32.'.'.pathinfo($this->image, PATHINFO_EXTENSION));
    imagedestroy($im);

    return $crc32;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Read images for work.
   *
   * @return array
   */
  private function getImages()
  {
    $images = glob($this->basedir.'/'.$this->images);

    $this->checkSizes($images);

    return $images;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------