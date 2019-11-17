<?php
//----------------------------------------------------------------------------------------------------------------------
use Plaisio\Abc;

/**
 * Class TestPage.
 */
class TestPage extends Plaisio\Page\Page
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   */
  public function __construct()
  {
    parent::__construct();

    Abc::$assets->cssAppendSource('style1.css');
    Abc::$assets->cssAppendSource('/css/test/style2.css');
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
