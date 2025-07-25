<?php
declare(strict_types=1);

namespace Plaisio\Phing\Task;

use Phing\Exception\BuildException;
use Phing\Project;
use Phing\Task;

/**
 * Parent Phing task with all general methods and properties.
 */
abstract class PlaisioTask extends Task
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Whether to stop the build on errors.
   *
   * @var bool
   */
  protected bool $haltOnError = true;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * If $haltOnError is set throws a BuildException with, otherwise creates a log event with priority
   * Project::MSG_ERR.
   *
   * @param mixed ...$param The format and arguments similar as for
   *                        [sprintf](http://php.net/manual/function.sprintf.php)
   *
   * @throws BuildException
   */
  public function logError(): void
  {
    $args   = func_get_args();
    $format = array_shift($args);

    foreach ($args as &$arg)
    {
      if (!is_scalar($arg))
      {
        $arg = var_export($arg, true);
      }
    }

    if ($this->haltOnError)
    {
      throw new BuildException(vsprintf($format, $args));
    }
    else
    {
      $this->log(vsprintf($format, $args), Project::MSG_ERR);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Creates a log event with priority Project::MSG_INFO.
   *
   * @param mixed ...$param The format and arguments similar as for
   *                        [sprintf](http://php.net/manual/function.sprintf.php)
   */
  public function logInfo(): void
  {
    $args   = func_get_args();
    $format = array_shift($args);

    foreach ($args as &$arg)
    {
      if (!is_scalar($arg))
      {
        $arg = var_export($arg, true);
      }
    }

    $this->log(vsprintf($format, $args));
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Creates a log event with priority Project::MSG_VERBOSE.
   *
   * @param mixed ...$param The format and arguments similar as for
   *                        [sprintf](http://php.net/manual/function.sprintf.php)   *
   *
   */
  public function logVerbose(): void
  {
    $args   = func_get_args();
    $format = array_shift($args);

    foreach ($args as &$arg)
    {
      if (!is_scalar($arg))
      {
        $arg = var_export($arg, true);
      }
    }

    $this->log(vsprintf($format, $args), Project::MSG_VERBOSE);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Creates a log event with priority Project::MSG_WARN.
   *
   * @param mixed ...$param The format and arguments similar as for
   *                        [sprintf](http://php.net/manual/function.sprintf.php)
   */
  public function logWarning(): void
  {
    $args   = func_get_args();
    $format = array_shift($args);

    foreach ($args as &$arg)
    {
      if (!is_scalar($arg))
      {
        $arg = var_export($arg, true);
      }
    }

    $this->log(vsprintf($format, $args), Project::MSG_WARN);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Setter for XML attribute haltOnError.
   *
   * @param bool $haltOnError Whether to stop the build on errors.
   */
  public function setHaltOnError(bool $haltOnError): void
  {
    $this->haltOnError = $haltOnError;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
