<?php
//----------------------------------------------------------------------------------------------------------------------
/**
 * Unit Tests for testing optimize_css Task.
 */
class SpriteTest extends PHPUnit_Framework_TestCase
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Create sprite from folder test03.
   */
  public function testSprite01()
  {
    chdir(__DIR__."/test03");
    exec('../../bin/phing sprite');

    $this->assertFileExists('www/css/my-icons.css');
    $this->assertFileExists('www/images/my-icons-644553912.png');
  }
  
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Try create sprite from folder test03. Test for different extensions.
   */
  public function testSprite02()
  {
    chdir(__DIR__."/test04");
    exec('../../bin/phing sprite');

    $this->assertFileNotExists('www/css/my-icons.css');
    $this->assertFileNotExists('www/images/my-icons-644553912.png');
  }



  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Try create sprite from folder test03. Test for different sizes.
   */
  public function testSprite03()
  {
    chdir(__DIR__."/test05");
    exec('../../bin/phing sprite');

    $this->assertFileNotExists('www/css/my-icons.css');
    $this->assertFileNotExists('www/images/my-icons-644553912.png');
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
