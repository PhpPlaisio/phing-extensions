<?php
declare(strict_types=1);

use Plaisio\Kernel\Nub;

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

    Nub::$assets->cssAppendSource('style1.css');
    Nub::$assets->cssAppendSource('/css/test/style2.css');
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
