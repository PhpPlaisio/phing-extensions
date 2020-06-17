<?php
declare(strict_types=1);

namespace Plaisio\Phing\Task\Test\OptimizeJsTask\Test01\Test;

use Plaisio\Kernel\Nub;
use Plaisio\Page\CorePage;
use Plaisio\Page\Page as ParentPage;
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

    Nub::$nub->assets->jsAdmSetPageSpecificMain(__CLASS__);
    Nub::$nub->assets->jsAdmFunctionCall(__CLASS__, 'function1');
    Nub::$nub->assets->jsAdmFunctionCall(__CLASS__, 'function2', ['arg1',
                                                                  strlen(serialize($this)),
                                                                  '"',
                                                                  "'"]);
    Nub::$nub->assets->jsAdmFunctionCall('Foo/Bar', 'function1', ['arg1', 'arg2']);
    Nub::$nub->assets->jsAdmFunctionCall(__CLASS__, 'function2', ['arg1', 'arg2']);
    Nub::$nub->assets->jsAdmFunctionCall(CorePage::class, 'function3', ['arg1', 'arg2']);
    Nub::$nub->assets->jsAdmFunctionCall(OtherPage::class, 'function4', ['arg1', 'arg2']);
    Nub::$nub->assets->jsAdmFunctionCall(ParentPage::class, 'function5', ['arg1', 'arg2']);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public function handleRequest(): Response
  {
    throw new \LogicException('Not implemented');
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
/* ID: index.php */
