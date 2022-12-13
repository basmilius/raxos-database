<?php
declare(strict_types=1);

namespace Raxos\Database\Connector;

use JetBrains\PhpStorm\Pure;
use JsonSerializable;
use PDO;
use PDOException;
use Raxos\Database\Error\DatabaseException;
use Raxos\Foundation\PHP\MagicMethods\DebugInfoInterface;

/**
 * Class Connector
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Connector
 * @since 1.0.0
 */
abstract class Connector implements DebugInfoInterface, JsonSerializable
{

    private const DEFAULT_OPTIONS = [
        PDO::ATTR_CASE => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_STRINGIFY_FETCHES => false
    ];

    public readonly array $options;

    /**
     * Connector constructor.
     *
     * @param string $dsn
     * @param string|null $username
     * @param string|null $password
     * @param array $options
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    public function __construct(
        public readonly string $dsn,
        public readonly ?string $username = null,
        public readonly ?string $password = null,
        array $options = []
    )
    {
        $this->options = self::DEFAULT_OPTIONS + $options;
    }

    /**
     * Creates a new PDO instance.
     *
     * @return PDO
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see PDO
     */
    public function createInstance(): PDO
    {
        try {
            return new PDO(
                $this->dsn,
                $this->username,
                $this->password,
                $this->options
            );
        } catch (PDOException $err) {
            throw DatabaseException::throw($err->getCode(), $err->getMessage(), $err);
        }
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    public function __debugInfo(): ?array
    {
        return null;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    #[Pure]
    public final function jsonSerialize(): string
    {
        return '-- hidden --';
    }

}
