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
    $this->configureProject(__DIR__.'/test01/build.xml');
    $this->project->setBasedir(__DIR__.'/test01');
    $this->executeTarget('sprite');

    $expected = file_get_contents(__DIR__.'/test01/www/css/navigation-expected.css');
    $actual   = file_get_contents(__DIR__.'/test01/www/css/navigation.css');

    $expected = preg_replace('|/images/navigation-[0-9a-f]*\.png|', '/images/navigation.png', $expected);
    $actual   = preg_replace('|/images/navigation-[0-9a-f]*\.png|', '/images/navigation.png', $actual);

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
