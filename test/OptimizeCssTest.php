<?php
//----------------------------------------------------------------------------------------------------------------------
/**
 * Unit Tests for testing optimize_css Task.
 */
class OptimizeCssTest extends PHPUnit_Framework_TestCase
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Optimizing all files inside folder test01 and then compare files.
   */
  public function testOptimizeCss()
  {
    chdir(__DIR__."/test01");
    exec('../../bin/phing optimize_css');

    $get_array = function ($folder)
    {
      $rootpath = getcwd().'/'.$folder;
      $array    = [];
      $files    = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootpath));
      foreach ($files as $fullpath => $file)
      {
        if ($file->isFile())
        {
          if (preg_match('/(\/\*\s?)(ID:\s?)([^\s].+)(\s?\*\/)/', file_get_contents($fullpath), $match))
          {
            $array[$match[3]] = $fullpath;
          }
        }
      }

      return $array;
    };

    $build    = $get_array('build');
    $expected = $get_array('expected');

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
