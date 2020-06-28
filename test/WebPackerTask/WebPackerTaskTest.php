<?php
declare(strict_types=1);

namespace Plaisio\Phing\Task\Test\WebPackerTask;

use Webmozart\PathUtil\Path;

/**
 * Unit Tests for testing optimize_css Task.
 */
class WebPackerTaskTest extends \BuildFileTest
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test calls to $this->nub->assets are been replaced with calls to the optimized methods.
   */
  public function testWebPacker01(): void
  {
    $this->configureProject(__DIR__.'/Test01/build.xml');
    $this->project->setBasedir(__DIR__.'/Test01');
    $this->executeTarget('web_packer');

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
      self::assertFileEquals($expected[$key], $build[$key], $key);
    }

    // All images must be directly under the images directory.
    foreach ($images as $key)
    {
      $dir = Path::getDirectory(path::makeRelative($build[$key], __DIR__.'/Test01/build'));
      self::assertEquals('www/images', $dir, $key);
    }

    // All CSS must be directly under the css directory.
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
   * Test CSS in html files are been replaced.
   */
  public function testWebPacker02(): void
  {
    $this->configureProject(__DIR__.'/Test02/build.xml');
    $this->project->setBasedir(__DIR__.'/Test02');
    $this->executeTarget('web_packer');

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
      self::assertFileEquals($expected[$key], $build[$key], $key);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test hard coded paths to resources in resources are been replaced with hard coded paths to optimized and renamed
   * resources.
   */
  public function testWebPacker03(): void
  {
    $this->configureProject(__DIR__.'/Test03/build.xml');
    $this->project->setBasedir(__DIR__.'/Test03');
    $this->executeTarget('web_packer');

    $files  = ['index.xhtml', 'test.js', 'logo.png', 'style.css'];

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
   * Test hard coded paths to resources in sources are been replaced with hard coded paths to optimized and renamed
   * resources.
   */
  public function testWebPacker04(): void
  {
    $this->configureProject(__DIR__.'/Test04/build.xml');
    $this->project->setBasedir(__DIR__.'/Test04');
    $this->executeTarget('web_packer');

    $files  = ['mailer.php', 'reset.php', 'mail.css', 'reset.css'];

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
        if ($content===false) print_r("\nUnable to read file '%s'.\n", $path);
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
