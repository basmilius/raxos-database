<?php
declare(strict_types=1);

namespace Raxos\Database\Connector;

use JetBrains\PhpStorm\Pure;
use PDO;
use Raxos\Database\Error\{ConnectionException};

/**
 * Class MySqlConnector
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Connector
 * @since 1.0.0
 */
readonly class MySqlConnector extends Connector
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
    #[Pure]
    public function __construct(
        string $host,
        public string $database,
        ?string $username = null,
        ?string $password = null,
        int $port = 3306,
        array $options = []
    )
    {
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

        $options += [PDO::ATTR_EMULATE_PREPARES => false];

        parent::__construct($dsn, $username, $password, $options);
    }

    /**
     * Creates a new mysql connector instance from the given options.
     *
     * @param array $options
     *
     * @return static
     * @throws ConnectionException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function fromOptions(array $options): self
    {
        if (!isset($options['host'])) {
            throw ConnectionException::missingOption('host');
        }

        if (!isset($options['database'])) {
            throw ConnectionException::missingOption('database');
        }

        $username = $options['username'] ?? null;
        $password = $options['password'] ?? null;
        $port = $options['port'] ?? 3306;

        return new static($options['host'], $options['database'], $username, $password, $port, $options['options'] ?? []);
    }

}
