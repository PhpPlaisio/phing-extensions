<?php
//----------------------------------------------------------------------------------------------------------------------
/**
 * Unit Tests for testing optimize_css Task.
 */
class OptimizeCssTest extends PHPUnit_Framework_TestCase
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Get all files from directory and subdirectories.
   *
   * @param $theFolder string Expected or build folder
   *
   * @return array
   */
  private function getFilesById($theFolder)
  {
    $rootpath = getcwd().'/'.$theFolder;
    $array    = [];
    $files    = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootpath));
    foreach ($files as $fullpath => $file)
    {
      if ($file->isFile())
      {
        $content = file_get_contents($fullpath);
        if ($content===false) print_r("\nUnable to read file '%s'.\n", $fullpath);
        if (preg_match('/(\/\*\s?)(ID:\s?)([^\s].+)(\s?\*\/)/', $content, $match))
        {
          $array[$match[3]] = $fullpath;
        }
      }
    }

    return $array;
  }
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Optimizing all files inside folder test01 and then compare files.
   */
  public function testOptimizeCss01()
  {
    $this->markTestSkipped('/usr/bin/csso library required.');
    chdir(__DIR__."/test01");
    exec('../../bin/phing optimize_css');

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
    $this->markTestSkipped('/usr/bin/csso library required.');
    chdir(__DIR__."/test02");
    exec('../../bin/phing -verbose optimize_css');

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
}

//----------------------------------------------------------------------------------------------------------------------
