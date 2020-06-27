<?php
declare(strict_types=1);

/* ID: TestPage.php */

namespace Plaisio\Phing\Task\Test\WebPackerTask\Test01\src;

use Plaisio\Kernel\Nub;
use Plaisio\Page\CorePage;
use Plaisio\Page\Page as ParentPage;
use Plaisio\Response\Response;

/**
 * Class TestPage.
 */
abstract class TestPage extends CorePage
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   */
  public function __construct()
  {
    parent::__construct();

    Nub::$nub->assets->cssOptimizedPushSource('/css/f53c1ec9f8348039a6331138800a065b.css');
    Nub::$nub->assets->cssOptimizedPushSource('/css/cdd8b3236e463c5e6703892725e7903a.css');
    Nub::$nub->assets->cssOptimizedAppendSource('/css/a30786fb032f25643896fab1e11a89c0.css');
    Nub::$nub->assets->cssOptimizedAppendSource('/css/aad8fed9c71f62730bd84f6c194d8935.css');

    Nub::$nub->assets->jsAdmOptimizedSetMain('/js/cf4179d63eb2a47965357c3d84ee0c86.js');
    Nub::$nub->assets->jsAdmOptimizedFunctionCall('Plaisio/Phing/Task/Test/WebPackerTask/Test01/src/TestPage', 'function1');
    Nub::$nub->assets->jsAdmOptimizedFunctionCall('Plaisio/Phing/Task/Test/WebPackerTask/Test01/src/TestPage', 'function2', ['arg1',
                                                                  strlen(serialize($this)),
                                                                  '"',
                                                                  "'"]);
    Nub::$nub->assets->jsAdmOptimizedFunctionCall('Foo/Bar', 'function1', ['arg1', 'arg2']);
    Nub::$nub->assets->jsAdmOptimizedFunctionCall('Plaisio/Phing/Task/Test/WebPackerTask/Test01/src/TestPage', 'function2', ['arg1', 'arg2']);
    Nub::$nub->assets->jsAdmOptimizedFunctionCall('Plaisio/Page/CorePage', 'function3', ['arg1', 'arg2']);
    Nub::$nub->assets->jsAdmOptimizedFunctionCall('Plaisio/Phing/Task/Test/WebPackerTask/Test01/src/OtherPage', 'function4', ['arg1', 'arg2']);
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
