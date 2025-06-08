<?php
declare(strict_types=1);

namespace Plaisio\Phing\Task\Test\WebPackerTask;

use PHPUnit\Framework\TestCase;
use SetBased\Helper\ProgramExecution;
use Symfony\Component\Filesystem\Path;

/**
 * Unit Tests for testing optimize_css Task.
 */
class WebPackerTaskTest extends TestCase
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test calls to $this->nub->assets are been replaced with calls to the optimized methods.
   */
  public function testWebPacker01(): void
  {
    chdir(__DIR__.'/Test01');
    [$output, $status] = ProgramExecution::exec1([$_SERVER['PWD'].'/bin/phing', 'web_packer']);
    self::assertSame(0, $status);

    $scripts = ['CorePage.js', 'OtherPage.js', 'Page.js', 'TestPage.main.js'];
    $images  = ['style1.png', 'style2.gif', 'style3.jpg', 'style4.jpeg'];
    $styles  = ['style1.css', 'style2.css', 'style3.css', 'style4.css'];

    $build    = $this->getFilesById('build');
    $expected = $this->getFilesById('expected');

    // All files must be under the build directory.
    $files = array_merge($scripts, $images, $scripts, ['TestPage.php']);
    foreach ($files as $key)
    {
      self::assertArrayHasKey($key, $build, $key);
    }

    // All files must be equal to the expected file.
    foreach ($build as $key => $b)
    {
      self::assertFileEquals($expected[$key], $b, $key);
    }

    // All images must be directly under the images directory.
    foreach ($images as $key)
    {
      $dir = Path::getDirectory(path::makeRelative($build[$key], __DIR__.'/Test01/build'));
      self::assertEquals('www/images', $dir, $key);
    }

    // All CSS must be directly under the CSS directory.
    foreach ($styles as $key)
    {
      $dir = Path::getDirectory(path::makeRelative($build[$key], __DIR__.'/Test01/build'));
      self::assertEquals('www/css', $dir, $key);
    }

    // All JS files must be directly under the js directory.
    foreach ($scripts as $key)
    {
      $dir = Path::getDirectory(path::makeRelative($build[$key], __DIR__.'/Test01/build'));
      self::assertEquals('www/js', $dir, $key);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test CSS in HTML files are being replaced.
   */
  public function testWebPacker02(): void
  {
    chdir(__DIR__.'/Test02');
    [$output, $status] = ProgramExecution::exec1([$_SERVER['PWD'].'/bin/phing', 'web_packer']);
    self::assertSame(0, $status);

    $pages  = ['index1.html', 'index2.html'];
    $styles = ['style1.css', 'style2.css'];

    $build    = $this->getFilesById('build');
    $expected = $this->getFilesById('expected');

    // All files must be under the build directory.
    $files = array_merge($pages, $styles);
    foreach ($files as $key)
    {
      self::assertArrayHasKey($key, $build, $key);
    }

    // All files must be equal to the expected file.
    foreach ($build as $key => $b)
    {
      self::assertFileEquals($expected[$key], $b, $key);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test hard-coded paths to resources in resources are replaced with hard-coded paths to optimized and renamed
   * resources.
   */
  public function testWebPacker03(): void
  {
    chdir(__DIR__.'/Test03');
    [$output, $status] = ProgramExecution::exec1([$_SERVER['PWD'].'/bin/phing', 'web_packer']);
    self::assertSame(0, $status);

    $files = ['index.xhtml', 'test.js', 'logo.png', 'style.css'];

    $build    = $this->getFilesById('build');
    $expected = $this->getFilesById('expected');

    // All files must be under the build directory.
    foreach ($files as $key)
    {
      self::assertArrayHasKey($key, $build, $key);
    }

    // All files must be equal to the expected file.
    foreach ($build as $key => $b)
    {
      self::assertFileEquals($expected[$key], $b, $key);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test hard-coded paths to resources in sources are being replaced with hard-coded paths to optimized and renamed
   * resources.
   */
  public function testWebPacker04(): void
  {
    chdir(__DIR__.'/Test04');
    [$output, $status] = ProgramExecution::exec1([$_SERVER['PWD'].'/bin/phing', 'web_packer']);
    self::assertSame(0, $status);

    $files = ['mailer.php', 'reset.php', 'mail.css', 'reset.css'];

    $build    = $this->getFilesById('build');
    $expected = $this->getFilesById('expected');

    // All files must be under the build directory.
    foreach ($files as $key)
    {
      self::assertArrayHasKey($key, $build, $key);
    }

    // All files must be equal to the expected file.
    foreach ($build as $key => $b)
    {
      self::assertFileEquals($expected[$key], $b, $key);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test multiple references on a single line in a CSS file are being replaced with references to the optimized
   * resources.
   */
  public function testWebPacker05(): void
  {
    chdir(__DIR__.'/Test05');
    [$output, $status] = ProgramExecution::exec1([$_SERVER['PWD'].'/bin/phing', 'web_packer']);
    self::assertSame(0, $status);

    $files = ['index.xhtml', 'background.css', 'red.png', 'white.png', 'blue.png'];

    $build    = $this->getFilesById('build');
    $expected = $this->getFilesById('expected');

    // All files must be under the build directory.
    foreach ($files as $key)
    {
      self::assertArrayHasKey($key, $build, $key);
    }

    // All files must be equal to the expected file.
    foreach ($build as $key => $b)
    {
      self::assertFileEquals($expected[$key], $b, $key);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test multiple references on a single line in a JS file are being replaced with references to the optimized
   * resources.
   */
  public function testWebPacker06(): void
  {
    chdir(__DIR__.'/Test06');
    [$output, $status] = ProgramExecution::exec1([$_SERVER['PWD'].'/bin/phing', 'web_packer']);
    self::assertSame(0, $status);

    $files = ['index.xhtml', 'background.js', 'red.png', 'white.png', 'blue.png'];

    $build    = $this->getFilesById('build');
    $expected = $this->getFilesById('expected');

    // All files must be under the build directory.
    foreach ($files as $key)
    {
      self::assertArrayHasKey($key, $build, $key);
    }

    // All files must be equal to the expected file.
    foreach ($build as $key => $b)
    {
      self::assertFileEquals($expected[$key], $b, $key);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test missing resource in a JS file yields an exception.
   */
  public function testWebPacker07(): void
  {
    chdir(__DIR__.'/Test07');
    $status = ProgramExecution::exec2([$_SERVER['PWD'].'/bin/phing', 'web_packer'], 'output.txt', null, null);

    $output = file_get_contents('output.txt');

    self::assertNotSame(0, $status);
    self::assertStringContainsString("Unable to find resource '/images/no-such-image.png'", $output);

    unlink('output.txt');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test missing resource in a PHP file yields an exception.
   */
  public function testWebPacker08(): void
  {
    chdir(__DIR__.'/Test08');
    $status = ProgramExecution::exec2([$_SERVER['PWD'].'/bin/phing', 'web_packer'], 'output.txt', null, null);

    $output = file_get_contents('output.txt');

    self::assertNotSame(0, $status);
    self::assertStringContainsString("Unable to find resource '/images/no-such-image.png'", $output);

    unlink('output.txt');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test directories are not seen as files.
   */
  public function testWebPacker09(): void
  {
    chdir(__DIR__.'/Test09');
    [$output, $status] = ProgramExecution::exec1([$_SERVER['PWD'].'/bin/phing', 'web_packer']);
    self::assertSame(0, $status);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test multiple references to the same resource on one line in a resource are allowed.
   */
  public function testWebPacker10(): void
  {
    chdir(__DIR__.'/Test10');
    [$output, $status] = ProgramExecution::exec1([$_SERVER['PWD'].'/bin/phing', 'web_packer']);
    self::assertSame(0, $status);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test config of require.js can be found in a main.js file.
   */
  public function testWebPacker11(): void
  {
    chdir(__DIR__.'/Test11');
    [$output, $status] = ProgramExecution::exec1([$_SERVER['PWD'].'/bin/phing', 'web_packer']);
    self::assertSame(0, $status);

    $files = ['index.xhtml'];

    $build = $this->getFilesById('build');

    // All files must be under the build directory.
    foreach ($files as $key)
    {
      self::assertArrayHasKey($key, $build, $key);
    }

    $content = file_get_contents($build['index.xhtml']);
    $n       = preg_match('/"(\/js\/.*.js)"/', $content, $matches);
    self::assertSame(1, $n);

    $content = file_get_contents(__DIR__.'/Test11/build/www/'.$matches[1]);
    self::assertStringContainsString("var requirejs, require, define;", $content, 'requireJS included');
    self::assertStringContainsString("define('Foo/Page'", $content, 'Foo/Page is defined');
    self::assertStringContainsString("requirejs.config(", $content, 'config is included');
    self::assertStringContainsString("require(['Foo/Page']", $content, 'Foo/Page.inti is called');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test all paths in config of require.js can be found on the filesystem.
   */
  public function testWebPacker12(): void
  {
    chdir(__DIR__.'/Test12');
    [$output, $status] = ProgramExecution::exec1([$_SERVER['PWD'].'/bin/phing', 'web_packer']);
    self::assertSame(0, $status);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test when a path in config of require.js cannot be found on the filesystem an error.
   */
  public function testWebPacker13(): void
  {
    chdir(__DIR__.'/Test13');
    $status = ProgramExecution::exec2([$_SERVER['PWD'].'/bin/phing', 'web_packer'], 'output.txt', null, null);

    $output = file_get_contents('output.txt');

    self::assertNotSame(0, $status);
    self::assertStringContainsString("Path 'nope: not/here' ('www/js/not/here.js') in file 'www/js/Foo/Page.main.js' does not exist.",
                                     $output);

    unlink('output.txt');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test when a path in config of require.js cannot be found on the filesystem an error.
   */
  public function testWebPacker14(): void
  {
    chdir(__DIR__.'/Test14');
    [$output, $status] = ProgramExecution::exec1([$_SERVER['PWD'].'/bin/phing', 'web_packer']);
    self::assertSame(0, $status);

    $files = ['main.sdoc', 'icon.png', 'figure1.jpg'];

    $build    = $this->getFilesById('build');
    $expected = $this->getFilesById('expected');

    // All files must be under the build directory.
    foreach ($files as $key)
    {
      self::assertArrayHasKey($key, $build, $key);
    }

    // All files must be equal to the expected file.
    foreach ($build as $key => $b)
    {
      self::assertFileEquals($expected[$key], $build[$key], $key);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Get all files from directory and subdirectories.
   *
   * @param string $folder Expected or build folder
   *
   * @return array
   */
  private function getFilesById(string $folder): array
  {
    $root  = getcwd().'/'.$folder;
    $list  = [];
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
    foreach ($files as $path => $file)
    {
      if ($file->isFile())
      {
        $content = file_get_contents($path);
        if ($content===false)
        {
          print_r("\nUnable to read file '%s'.\n", $path);
        }
        if (preg_match('/(\/\*\s?)(ID:\s?)(?<ID>[^\s]+)(\s?\*\/)/', $content, $match))
        {
          $list[$match['ID']] = $path;
        }
      }
    }

    ksort($list);

    return $list;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
