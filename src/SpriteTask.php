<?php
declare(strict_types=1);

namespace Plaisio\Phing\Task;

use Phing\Type\Element\FileSetAware;
use SetBased\Helper\Cast;

/**
 * Get images and create one big sprite and css in result.
 */
class SpriteTask extends PlaisioTask
{
  //--------------------------------------------------------------------------------------------------------------------
  use FileSetAware;

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
   * The list of paths to images to be included in the sprite image.
   *
   * @var array
   */
  private $imagePaths;

  /**
   * Image width.
   *
   * @var int
   */
  private $imageWidth;

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
    if ($integer===0) return '0';

    return Cast::toManString($integer).'px';
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Main method of this Phing task.
   */
  public function main()
  {
    $this->createImageList();
    $this->validateParameters();

    [$matrix, $rows, $cols] = $this->makeImageMatrix();

    $this->validateImageSizes($matrix);
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
    $css[] = '  display: inline-block;';
    $css[] = sprintf('  width: %s;', self::lengthToPixel($this->imageWidth));
    $css[] = sprintf('  height: %s;', self::lengthToPixel($this->imageHeight));
    $css[] = '}';
    $css[] = '';

    foreach ($matrix as $element)
    {
      $css[] = sprintf('.%s-%s', $this->cssBaseClass, pathinfo($element['path'], PATHINFO_FILENAME));
      $css[] = '{';
      $css[] = sprintf('  background-position: %s %s;',
                       self::lengthToPixel(-$this->imageWidth * $element['x']),
                       self::lengthToPixel(-$this->imageHeight * $element['y']));
      $css[] = '}';
      $css[] = '';
    }

    $path = $this->resourceRoot.'/'.$this->cssFilename;
    $this->logInfo('Creating CSS %s', $path);
    file_put_contents($path, implode(PHP_EOL, $css));
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Create the list of images to be include in the sprite image.
   */
  private function createImageList()
  {
    $cwd = realpath(getcwd()).DIRECTORY_SEPARATOR;

    $this->imagePaths = [];
    foreach ($this->getFileSets() as $fileSet)
    {
      foreach ($fileSet as $filename)
      {
        if (strncmp($cwd, $filename, strlen($cwd))===0)
        {
          $this->imagePaths[] = substr($filename, strlen($cwd));
        }
        else
        {
          $this->imagePaths[] = $filename;
        }
      }
    }

    sort($this->imagePaths);
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
    $sprite = imagecreatetruecolor($this->imageWidth * $cols, $this->imageHeight * $rows);

    // Add alpha channel to image (transparency)
    imagesavealpha($sprite, true);
    $alpha = imagecolorallocatealpha($sprite, 0, 0, 0, 127);
    imagefill($sprite, 0, 0, $alpha);

    // Append images to sprite and generate CSS lines
    foreach ($matrix as $element)
    {
      $this->logVerbose('Reading image %s', $element['path']);

      $data = file_get_contents($element['path']);
      $icon = imagecreatefromstring($data);
      imagecopy($sprite,
                $icon,
                $this->imageWidth * $element['x'],
                $this->imageHeight * $element['y'],
                0,
                0,
                $this->imageWidth,
                $this->imageHeight);
    }

    $this->logVerbose('Creating sprite image %s', $this->spriteFilename);
    $this->saveSpriteImage($sprite);
    imagedestroy($sprite);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns all images as a matrix.
   *
   * @return array
   */
  private function makeImageMatrix(): array
  {
    $rows   = Cast::toManInt(round(sqrt(sizeof($this->imagePaths))));
    $cols   = Cast::toManInt(round(sizeof($this->imagePaths) / $rows + 0.5));
    $matrix = [];
    $x      = 0;
    $y      = 0;
    foreach ($this->imagePaths as $path)
    {
      if ($x===$cols)
      {
        $x = 0;
        $y++;
      }

      $matrix[] = ['x'    => $x,
                   'y'    => $y,
                   'path' => $path];

      $x++;
    }

    return [$matrix, $cols, $rows];
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
   * Saves the sprite image.
   *
   * @param resource $sprite The sprite image.
   */
  private function saveSpriteImage($sprite): void
  {
    $extension = mb_strtolower(pathinfo($this->spriteFilename, PATHINFO_EXTENSION) ?? '');
    switch ($extension)
    {
      case 'gif':
        imagegif($sprite, $this->spriteFilename);
        break;

      case 'jpg':
      case 'jpeg':
        imagejpeg($sprite, $this->spriteFilename, 100);
        break;

      case 'png':
        imagepng($sprite, $this->spriteFilename, 9);
        break;

      case 'webp':
        imagewebp($sprite, $this->spriteFilename, 100);
        break;

      default:
        $this->logError('Unknown image format');
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Validates all images have same sizes.
   *
   * @param array[] $matrix Images.
   */
  private function validateImageSizes(array $matrix): void
  {
    foreach ($matrix as $element)
    {
      [$width, $height] = getimagesize($element['path']);

      if ($this->imageHeight===null)
      {
        $this->imageHeight = $height;
      }
      if ($this->imageWidth===null)
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

    if (empty($this->imagePaths))
    {
      $this->logError('No image list provided');
    }

    $extension  = mb_strtolower(pathinfo($this->spriteFilename, PATHINFO_EXTENSION) ?? '');
    $imageTypes = imagetypes();
    switch ($extension)
    {
      case 'gif':
        if (!($imageTypes & IMG_GIF))
        {
          $this->logError('GIF not supported');
        }
        break;

      case 'jpg':
      case 'jpeg':
        if (!($imageTypes & IMG_JPEG))
        {
          $this->logError('JPEG not supported');
        }
        break;

      case 'png':
        if (!($imageTypes & IMG_PNG))
        {
          $this->logError('PNG not supported');
        }
        break;

      case 'webp':
        if (!($imageTypes & IMG_WEBP))
        {
          $this->logError('WebP not supported');
        }
        break;

      default:
        $this->logError('Unknown image format');
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
