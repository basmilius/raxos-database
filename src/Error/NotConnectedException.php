<?php
declare(strict_types=1);

namespace Raxos\Database\Error;

use PDOException;
use Raxos\Contract\Database\DatabaseExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class NotConnectedException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Error
 * @since 2.0.0
 */
final class NotConnectedException extends Exception implements DatabaseExceptionInterface
{

    /**
     * Class NotConnectedException
     *
     * @param PDOException|null $err
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public readonly ?PDOException $err = null
    )
    {
        parent::__construct(
            'db_not_connected',
            'Not connected to the server.',
            previous: $err
        );
    }

}
