<?php
declare(strict_types=1);

namespace Plaisio\Phing\Task\Test\OptimizeCssTask;

/**
 * Unit Tests for testing optimize_css Task.
 */
class OptimizeCssTaskTest extends \BuildFileTest
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Optimizing all files inside folder test01 and then compare files.
   */
  public function testOptimizeCss01()
  {
    $this->configureProject(__DIR__.'/test01/build.xml');
    $this->project->setBasedir(__DIR__.'/test01');
    $this->executeTarget('optimize_css');

    $build    = $this->getFilesById('build');
    $expected = $this->getFilesById('expected');

    foreach ($expected as $key => $b)
    {
      if (isset($build[$key]) && isset($expected[$key]))
      {
        $this->assertFileEquals($expected[$key], $build[$key]);
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Optimizing all files inside folder test01 and then compare files.
   */
  public function testOptimizeCss02()
  {
    $this->configureProject(__DIR__.'/test02/build.xml');
    $this->project->setBasedir(__DIR__.'/test02');
    $this->executeTarget('optimize_css');

    $this->configureProject('build.xml');
    $this->executeTarget('optimize_css');

    $build    = $this->getFilesById('build');
    $expected = $this->getFilesById('expected');

    foreach ($expected as $key => $b)
    {
      if (isset($build[$key]) && isset($expected[$key]))
      {
        $this->assertFileEquals($expected[$key], $build[$key]);
      }
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
  private function getFilesById($folder)
  {
    $root_path = getcwd().'/'.$folder;
    $array     = [];
    $files     = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root_path));
    foreach ($files as $full_path => $file)
    {
      if ($file->isFile())
      {
        $content = file_get_contents($full_path);
        if ($content===false) print_r("\nUnable to read file '%s'.\n", $full_path);
        if (preg_match('/(\/\*\s?)(ID:\s?)([^\s].+)(\s?\*\/)/', $content, $match))
        {
          $array[$match[3]] = $full_path;
        }
      }
    }

    return $array;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
