<?php
declare(strict_types=1);

namespace Plaisio\Phing\Task\Test\SpriteTask;

/**
 * Unit Tests for testing sprite Task.
 */
class SpriteTaskTest extends \BuildFileTest
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Optimizing all files inside folder test01 and then compare files.
   */
  public function testSprite01()
  {
    $imageTypes = imagetypes();
    $dir = ($imageTypes & IMG_WEBP) ? 'test01-webp' : 'test01-png';
    $this->configureProject(sprintf('%s/%s/%s', __DIR__, $dir, 'build.xml'));
    $this->project->setBasedir(sprintf('%s/%s', __DIR__, $dir));
    $this->executeTarget('sprite');

    $expected = file_get_contents(sprintf('%s/%s/%s', __DIR__, $dir, 'www/css/navigation-expected.css'));
    $actual   = file_get_contents(sprintf('%s/%s/%s', __DIR__, $dir, 'www/css/navigation.css'));

    self::assertSame($expected, $actual);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Optimizing all files inside folder test01 and then compare files.
   */
  public function testSprite02()
  {
    $this->expectException('BuildException');
    $this->expectExceptionMessage('Images have different sizes');

    $this->configureProject(__DIR__.'/test02/build.xml');
    $this->project->setBasedir(__DIR__.'/test02');
    $this->executeTarget('sprite');
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
