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
  private $resourceroot = '';

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
    $this->getImages();
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
   * @param mixed $resourceroot
   */
  public function setResourceroot($resourceroot)
  {
    $this->resourceroot = $resourceroot;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Create css file for work with sprite.
   *
   * @param array  $imgArray Array with paths for images.
   * @param string $crc32    Hash from new sprite.
   */
  private function createCss($imgArray, $crc32)
  {
    $cssStyles = '';
    foreach ($imgArray as $file)
    {
      if ($cssStyles) $cssStyles .= ',';
      $cssStyles .= '.my-icons-'.basename($file, '.'.$this->extension);
    }
    $cssStyles .= '{ background-image: url('.$this->basedir.'/'.basename($this->image, ',png').'-'.$crc32.'.png) no-repeat; }';
    $yi = 0;
    $xi = 0;
    foreach ($imgArray as $file)
    {
      if ($xi==2)
      {
        $xi = 0;
        $yi++;
      }
      $cssStyles .= '.my-icons-'.basename($file, '.'.$this->extension).' { background-position: -'.($this->imageWidth * $xi).'px -'.($this->imageHeight * $yi).'px; }';
      $xi++;
    }
    $fp = fopen($this->resourceroot.'/'.$this->css, 'w');
    fwrite($fp, $cssStyles);
    fclose($fp);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Create one square sprite from images.
   *
   * @param array $imgArray Array with paths for images.
   *
   * @return int
   */
  private function createSprite($imgArray)
  {
    $im = imagecreatetruecolor($this->imageWidth * 2, $this->imageHeight * 2);

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
      if ($xi==2)
      {
        $xi = 0;
        $yi++;
      }
      imagecopy($im, $im2, ($this->imageWidth * $xi), ($this->imageHeight * $yi), 0, 0, $this->imageWidth, $this->imageHeight);
      $xi++;
    }
    // Save image to file
    $imagePath = $this->resourceroot.'/'.$this->image;
    imagepng($im, $imagePath);
    $crc32 = crc32(file_get_contents($imagePath));
    unlink($imagePath);
    imagepng($im, $this->resourceroot.'/'.pathinfo($this->image, PATHINFO_DIRNAME).'/'.basename($this->image, '.png').'-'.$crc32.'.'.pathinfo($this->image, PATHINFO_EXTENSION));
    imagedestroy($im);

    return $crc32;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Read images for work.
   */
  private function getImages()
  {
    $imgArray = [];
    $images   = glob($this->basedir.'/'.$this->images);

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

      if (!$this->extension)
      {
        $this->extension = pathinfo($image, PATHINFO_EXTENSION);
      }
      if ($this->extension!==pathinfo($image, PATHINFO_EXTENSION))
      {
        $this->log('Images have different extensions.', Project::MSG_ERR);
        exit(0);
      }
      $imgArray[] = $image;
    }
    asort($imgArray);

    $crc32 = $this->createSprite($imgArray);
    $this->createCss($imgArray, $crc32);
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------