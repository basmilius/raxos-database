<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Error;

use Raxos\Database\Error\DatabaseException;

/**
 * Class OrmException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Error
 * @since 1.0.17
 */
abstract class OrmException extends DatabaseException {}
