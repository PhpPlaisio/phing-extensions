<?php
//----------------------------------------------------------------------------------------------------------------------
class TestPage extends SetBased\Abc\Page\Page
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   */
  public function __construct()
  {
    parent::__construct();

    $this->cssAppendSource('/css/style1.css');
    $this->cssAppendSource('/css/test/style2.css');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Must be implemented in child classes to echo the actual page content, i.e. the inner HTML of the body tag.
   *
   * @return null
   */
  public function echoPage()
  {
    // TODO: Implement echoPage() method.
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
/* ID: index.php */