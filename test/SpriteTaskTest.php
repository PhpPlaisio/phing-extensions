<?php
//----------------------------------------------------------------------------------------------------------------------
/**
 * Unit Tests for testing optimize_css Task.
 */
class SpriteTaskTest extends PHPUnit_Framework_TestCase
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Create sprite from folder test01.
   */
  public function testSprite01()
  {
    chdir(__DIR__."/SpriteTask/test01");
    exec('../../../bin/phing sprite');

    $this->assertFileExists('www/css/my-icons.css');
    $this->assertFileExists('www/images/my-icons-644553912.png');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test for different extensions.
   */
  public function testSprite02()
  {
    chdir(__DIR__."/SpriteTask/test02");
    exec('../../../bin/phing sprite');

    $this->assertFileExists('www/css/my-icons.css');
    $this->assertFileExists('www/images/my-icons-644553912.png');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test for different sizes.
   */
  public function testSprite03()
  {
    chdir(__DIR__."/SpriteTask/test03");
    exec('../../../bin/phing sprite');

    $this->assertFileNotExists('www/css/my-icons.css');
    $this->assertFileNotExists('www/images/my-icons-644553912.png');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test for five images.
   */
  public function testSprite04()
  {
    chdir(__DIR__."/SpriteTask/test04");
    exec('../../../bin/phing sprite');

    $this->assertFileExists('www/css/my-icons.css');
    $this->assertFileExists('www/images/my-icons-3277839285.png');
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
