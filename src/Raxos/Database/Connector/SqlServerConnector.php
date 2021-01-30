<?php
declare(strict_types=1);

namespace Raxos\Database\Connector;

/**
 * Class SqlServerConnector
 *
 * @package Raxos\Database\Connector
 */
class SqlServerConnector extends Connector
{
    public function __construct(string $host, private string $database, private string $schema = 'dbo', ?string $username = null, ?string $password = null, int $port = 1433, array $options = [])
    {
        $dsn = "sqlsrv:Server=$host, $port;Database={$database}";

        parent::__construct($dsn, $username, $password, $options);
    }

    public final function getDatabase(): string
    {
        return $this->database;
    }

    public final function getSchema(): string
    {
        return $this->schema;
    }
}
