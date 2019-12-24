<?php
declare(strict_types=1);

use SetBased\Helper\Cast;

/**
 * Get images and create one big sprite and css in result.
 */
class SpriteTask extends \PlaisioTask
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The base class name for all CSS classes.
   *
   * @var string
   */
  private $cssBaseClass;

  /**
   * The path to the generated CSS file.
   *
   * @var string
   */
  private $cssFilename;

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
  private $images;

  /**
   * Resource directory.
   *
   * @var string
   */
  private $resourceRoot;

  /**
   * The path to the generated sprite image.
   *
   * @var string
   */
  private $spriteFilename;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Converts a pixel offset to a string with 'px'.
   *
   * @param int $integer The integer value.
   *
   * @return string
   */
  private static function lengthToPixel(int $integer): string
  {
    if ($integer==0) return '0';

    return Cast::toManString($integer).'px';
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Main method of this Phing task.
   */
  public function main()
  {
    $this->validateParameters();

    [$matrix, $rows, $cols] = $this->getImages();

    $this->checkSizes($matrix);
    $this->createSprite($matrix, $rows, $cols);
    $this->createCssFile($matrix);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for the base class for all CSS classes.
   *
   * @param string $cssBaseClass The base class for all CSS classes.
   */
  public function setCssBaseClass(string $cssBaseClass): void
  {
    $this->cssBaseClass = $cssBaseClass;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for path to the generated CSS file.
   *
   * @param string $cssFilename The path to the generated CSS file.
   */
  public function setCssFilename(string $cssFilename): void
  {
    $this->cssFilename = $cssFilename;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for images path.
   *
   * @param string $images
   */
  public function setImages(string $images): void
  {
    $this->images = $images;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for resource root.
   *
   * @param string $resourceRoot
   */
  public function setResourceRoot(string $resourceRoot): void
  {
    $this->resourceRoot = $resourceRoot;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for the path to the generated sprite image.
   *
   * @param string $spriteFilename
   */
  public function setSpriteFilename(string $spriteFilename): void
  {
    $this->spriteFilename = $spriteFilename;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Check sizes.
   *
   * @param array[] $matrix Images.
   */
  private function checkSizes(array $matrix): void
  {
    foreach ($matrix as $element)
    {
      $data   = getimagesize($element['image']);
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
        $this->logError('Images have different sizes');
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Create css file for work with sprite.
   *
   * @param array[] $matrix Array with paths for images.
   */
  private function createCssFile(array $matrix): void
  {
    $css = [];

    $css[] = sprintf('.%s', $this->cssBaseClass);
    $css[] = '{';
    $css[] = sprintf('  background-image: url(%s);', $this->resolveSpriteUrl());
    $css[] = '  display: table-cell;';
    $css[] = sprintf('  width: %s;', self::lengthToPixel($this->imageWidth));
    $css[] = sprintf('  height: %s;', self::lengthToPixel($this->imageHeight));
    $css[] = '}';
    $css[] = '';

    foreach ($matrix as $element)
    {
      $css[] = sprintf('.%s-%s', $this->cssBaseClass, pathinfo($element['image'], PATHINFO_FILENAME));
      $css[] = '{';
      $css[] = sprintf('  background-position: %s %s;',
                       self::lengthToPixel(-$this->imageWidth * $element['x']),
                       self::lengthToPixel(-$this->imageHeight * $element['y']));
      $css[] = '}';
      $css[] = '';
    }

    file_put_contents($this->resourceRoot.'/'.$this->cssFilename, implode(PHP_EOL, $css));
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Create one square sprite from images.
   *
   * @param array[] $matrix Array with paths for images.
   * @param int     $cols   The number of columns in the matrix.
   * @param int     $rows   The number of rows in the matrix.
   */
  private function createSprite(array $matrix, int $cols, int $rows): void
  {
    $im = imagecreatetruecolor($this->imageWidth * $cols, $this->imageHeight * $rows);

    // Add alpha channel to image (transparency)
    imagesavealpha($im, true);
    $alpha = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagefill($im, 0, 0, $alpha);

    // Append images to sprite and generate CSS lines
    foreach ($matrix as $element)
    {
      $im2 = imagecreatefrompng($element['image']);
      imagecopy($im,
                $im2,
                $this->imageWidth * $element['x'],
                $this->imageHeight * $element['y'],
                0,
                0,
                $this->imageWidth,
                $this->imageHeight);
    }

    imagepng($im, $this->spriteFilename, 9);
    imagedestroy($im);

    $this->renameSpriteFile();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Read images for work.
   *
   * @return array
   */
  private function getImages(): array
  {
    $images = glob($this->images);

    $rows   = Cast::toManInt(round(sqrt(sizeof($images))));
    $cols   = Cast::toManInt(round(sizeof($images) / $rows));
    $matrix = [];
    $x      = 0;
    $y      = 0;
    foreach ($images as $image)
    {
      if ($x==$cols)
      {
        $x = 0;
        $y++;
      }

      $matrix[] = ['x'     => $x,
                   'y'     => $y,
                   'image' => $image];

      $x++;
    }

    return [$matrix, $cols, $rows];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Renames the sprite file using the md5 hash of the sprite file.
   */
  private function renameSpriteFile(): void
  {
    $md5 = md5_file($this->spriteFilename);

    $info              = pathinfo($this->spriteFilename);
    $newSprintFilename = sprintf("%s/%s-%s.%s",
                                 $info['dirname'],
                                 $info['filename'],
                                 $md5,
                                 $info['extension']);

    rename($this->spriteFilename, $newSprintFilename);

    $this->spriteFilename = $newSprintFilename;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the relative URL ot the sprite image.
   *
   * @return string
   */
  private function resolveSpriteUrl(): string
  {
    $resourcePath = realpath($this->resourceRoot);
    $spritePath   = realpath($this->spriteFilename);

    if (strncmp($resourcePath, $spritePath, strlen($resourcePath)))
    {
      $this->logError("Sprite path '%s' must be under resource directory '%'", $spritePath, $resourcePath);
    }

    return substr($spritePath, strlen($resourcePath));
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Validates the given parameters.
   */
  private function validateParameters(): void
  {
    $parameters = ['cssBaseClass', 'cssFilename', 'spriteFilename', 'resourceRoot'];
    foreach ($parameters as $parameter)
    {
      if ($this->$parameter===null || $this->$parameter==='')
      {
        $this->logError('Parameter %s is mandatory', $parameter);
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
