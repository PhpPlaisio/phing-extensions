<?php
//----------------------------------------------------------------------------------------------------------------------
use SetBased\Abc\Abc;

/**
 * Class TestPage.
 */
class TestPage extends SetBased\Abc\Page\Page
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   */
  public function __construct()
  {
    parent::__construct();

    Abc::$assets->cssOptimizedAppendSource('/css/7224b5fd2f9d756a6ac576bbe070efba.0.css');

  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Must be implemented in child classes to echo the actual page content, i.e. the inner HTML of the body tag.
   */
  public function echoPage()
  {
    echo 'Hello, world';
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
/* ID: index.php */