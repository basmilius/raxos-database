<?php
declare(strict_types=1);

namespace Raxos\Database\Connector;

use Raxos\Database\Error\ConnectionException;
use Raxos\Database\Error\DatabaseException;

/**
 * Class MySqlConnector
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Connector
 * @since 1.0.0
 */
class MySqlConnector extends Connector
{

    /**
     * MySqlConnector constructor.
     *
     * @param string $host
     * @param string $database
     * @param string|null $username
     * @param string|null $password
     * @param int $port
     * @param array $options
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(string $host, private string $database, ?string $username = null, ?string $password = null, int $port = 3306, array $options = [])
    {
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

        parent::__construct($dsn, $username, $password, $options);
    }

    /**
     * Gets the database name.
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getDatabase(): string
    {
        return $this->database;
    }

    /**
     * Creates a new mysql connector instance from the given options.
     *
     * @param array $options
     *
     * @return static
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function fromOptions(array $options): self
    {
        if (!isset($options['host']))
            throw new ConnectionException('Missing the required host option.', ConnectionException::ERR_INCOMPLETE_OPTIONS);

        if (!isset($options['database']))
            throw new ConnectionException('Missing the required database option.', ConnectionException::ERR_INCOMPLETE_OPTIONS);

        $username = $options['username'] ?? null;
        $password = $options['password'] ?? null;
        $port = $options['port'] ?? 3306;

        return new static($options['host'], $options['database'], $username, $password, $port, $options['options'] ?? []);
    }

}
