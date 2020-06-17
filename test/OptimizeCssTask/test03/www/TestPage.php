<?php
declare(strict_types=1);

namespace Plaisio\Phing\Task\Test\OptimizeCssTask\test03\www;

use Plaisio\Page\CorePage;
use Plaisio\Response\Response;

/**
 * Class TestPage.
 */
class TestPage extends CorePage
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   */
  public function __construct()
  {
    parent::__construct();

    $this->nub->assets->cssAppendSource('style1.css');
    $this->nub->assets->cssAppendSource('/css/test/style2.css');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public function handleRequest(): Response
  {
    throw new LogicException('Not implemented');
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
/* ID: index.php */
