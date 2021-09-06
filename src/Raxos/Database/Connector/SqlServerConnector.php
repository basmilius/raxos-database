<?php
declare(strict_types=1);

namespace Raxos\Database\Connector;

use JetBrains\PhpStorm\Pure;
use PDO;

/**
 * Class SqlServerConnector
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Connector
 * @since 1.0.0
 */
class SqlServerConnector extends Connector
{

    /**
     * SqlServerConnector constructor.
     *
     * @param string $host
     * @param string $database
     * @param string $schema
     * @param string|null $username
     * @param string|null $password
     * @param int $port
     * @param array $options
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    public function __construct(string $host, private string $database, private string $schema = 'dbo', ?string $username = null, ?string $password = null, int $port = 1433, array $options = [])
    {
        parent::__construct("sqlsrv:Server={$host}, {$port}; Database={$database}", $username, $password, $options);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function createInstance(): PDO
    {
        $pdo = parent::createInstance();
        $pdo->setAttribute(PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE, true);

        return $pdo;
    }

    /**
     * Gets the database used.
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    public function getDatabase(): string
    {
        return $this->database;
    }

    /**
     * Gets the schema used.
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    public function getSchema(): string
    {
        return $this->schema;
    }

}
