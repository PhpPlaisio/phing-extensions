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

    Nub::$nub->assets->jsAdmOptimizedSetPageSpecificMain('/js/f080f452a5c8574d2507170ff8ea57e9.0.js');
    Nub::$nub->assets->jsAdmOptimizedFunctionCall('Plaisio/Phing/Task/Test/OptimizeJsTask/Test01/Test/TestPage', 'function1');
    Nub::$nub->assets->jsAdmOptimizedFunctionCall('Plaisio/Phing/Task/Test/OptimizeJsTask/Test01/Test/TestPage', 'function2', ['arg1',
                                                                  strlen(serialize($this)),
                                                                  '"',
                                                                  "'"]);
    Nub::$nub->assets->jsAdmOptimizedFunctionCall('Foo/Bar', 'function1', ['arg1', 'arg2']);
    Nub::$nub->assets->jsAdmOptimizedFunctionCall('Plaisio/Phing/Task/Test/OptimizeJsTask/Test01/Test/TestPage', 'function2', ['arg1', 'arg2']);
    Nub::$nub->assets->jsAdmOptimizedFunctionCall('Plaisio/Page/CorePage', 'function3', ['arg1', 'arg2']);
    Nub::$nub->assets->jsAdmOptimizedFunctionCall('Plaisio/Phing/Task/Test/OptimizeJsTask/Test01/Test/OtherPage', 'function4', ['arg1', 'arg2']);
    Nub::$nub->assets->jsAdmOptimizedFunctionCall('Plaisio/Page/Page', 'function5', ['arg1', 'arg2']);
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
