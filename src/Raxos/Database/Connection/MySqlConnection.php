<?php
declare(strict_types=1);

namespace Raxos\Database\Connection;

use Raxos\Database\Connector\Connector;
use Raxos\Database\Dialect\MySqlDialect;
use Raxos\Database\Logger\Logger;
use Raxos\Database\Orm\{Cache, CacheInterface};
use Raxos\Database\Query\MySqlQuery;

/**
 * Class MySqlConnection
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Connection
 * @since 1.0.0
 */
final class MySqlConnection extends AbstractMySqlLikeConnection
{

    /**
     * MySqlConnection constructor.
     *
     * @param string $id
     * @param Connector $connector
     * @param CacheInterface $cache
     * @param Logger $logger
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        string $id,
        Connector $connector,
        CacheInterface $cache = new Cache(),
        Logger $logger = new Logger()
    )
    {
        parent::__construct($id, $connector, new MySqlDialect(), $cache, $logger);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function query(bool $prepared = true): MySqlQuery
    {
        return new MySqlQuery($this, $prepared);
    }

}
