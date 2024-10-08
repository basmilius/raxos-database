<?php
declare(strict_types=1);

namespace Raxos\Database\Connector;

/**
 * Class SQLiteInMemoryConnector
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Connector
 * @since 1.2.0
 */
readonly class SQLiteInMemoryConnector extends SQLiteConnector
{

    /**
     * SQLiteInMemoryConnector constructor.
     *
     * @param array $options
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.2.0
     */
    public function __construct(array $options = [])
    {
        $dsn = 'sqlite::memory:';

        parent::__construct($dsn, options: $options);
    }

}
