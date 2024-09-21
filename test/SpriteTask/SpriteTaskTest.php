<?php
declare(strict_types=1);

namespace Plaisio\Phing\Task\Test\SpriteTask;

use Phing\Exception\BuildException;
use Phing\Support\BuildFileTestCase;

/**
 * Unit Tests for testing sprite Task.
 */
class SpriteTaskTest extends BuildFileTestCase
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Optimizing all files inside folder test01-png and then compare files.
   */
  public function testSprite01Png()
  {
    $dir = 'test01-png';
    $this->configureProject(sprintf('%s/%s/%s', __DIR__, $dir, 'build.xml'));
    $this->project->setBasedir(sprintf('%s/%s', __DIR__, $dir));
    $this->getProject()->executeTarget('sprite');

    $expected = file_get_contents(sprintf('%s/%s/%s', __DIR__, $dir, 'www/css/navigation-expected.css'));
    $actual   = file_get_contents(sprintf('%s/%s/%s', __DIR__, $dir, 'www/css/navigation.css'));

    self::assertSame($expected, $actual);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Optimizing all files inside folder test01 and then compare files.
   */
  public function testSprite01Webp()
  {
    $imageTypes = imagetypes();
    if ($imageTypes & IMG_WEBP)
    {
      $dir = 'test01-webp';
      $this->configureProject(sprintf('%s/%s/%s', __DIR__, $dir, 'build.xml'));
      $this->project->setBasedir(sprintf('%s/%s', __DIR__, $dir));
      $this->getProject()->executeTarget('sprite');

      $expected = file_get_contents(sprintf('%s/%s/%s', __DIR__, $dir, 'www/css/navigation-expected.css'));
      $actual   = file_get_contents(sprintf('%s/%s/%s', __DIR__, $dir, 'www/css/navigation.css'));

      self::assertSame($expected, $actual);
    }
    else
    {
      self::assertTrue(true);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Optimizing all files inside folder test01 and then compare files.
   */
  public function testSprite02()
  {
    $this->expectException(BuildException::class);
    $this->expectExceptionMessage('Images have different sizes');

    $this->configureProject(__DIR__.'/test02/build.xml');
    $this->project->setBasedir(__DIR__.'/test02');
    $this->getProject()->executeTarget('sprite');
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
