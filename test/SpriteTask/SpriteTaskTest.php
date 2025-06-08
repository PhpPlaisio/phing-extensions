<?php
declare(strict_types=1);

namespace Plaisio\Phing\Task\Test\SpriteTask;

use PHPUnit\Framework\TestCase;
use SetBased\Helper\ProgramExecution;

/**
 * Unit Tests for testing sprite Task.
 */
class SpriteTaskTest extends TestCase
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Optimizing all files inside folder test01-png and then compare files.
   */
  public function testSprite01Png()
  {
    $dir = 'test01-png';
    chdir(__DIR__.'/'.$dir);
    [$output, $status] = ProgramExecution::exec1([$_SERVER['PWD'].'/bin/phing', 'sprite']);
    self::assertSame(0, $status);

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
      chdir(__DIR__.'/'.$dir);
      [$output, $status] = ProgramExecution::exec1([$_SERVER['PWD'].'/bin/phing', 'sprite']);
      self::assertSame(0, $status);

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
    $dir = 'test02';
    chdir(__DIR__.'/'.$dir);
    $status = ProgramExecution::exec2([$_SERVER['PWD'].'/bin/phing', 'sprite'], 'output.txt', null, null);

    $output = file_get_contents('output.txt');

    self::assertNotSame(0, $status);
    self::assertStringContainsString('Images have different sizes.', $output);

    unlink('output.txt');
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
