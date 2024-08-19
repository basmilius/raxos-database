<?php
declare(strict_types=1);

namespace Raxos\Database\Connector;

use JsonSerializable;
use PDO;
use PDOException;
use Raxos\Database\Error\ConnectionException;
use Raxos\Foundation\Contract\DebuggableInterface;
use SensitiveParameter;

/**
 * Class Connector
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Connector
 * @since 1.0.0
 */
abstract readonly class Connector implements DebuggableInterface, JsonSerializable
{

    private const array DEFAULT_OPTIONS = [
        PDO::ATTR_CASE => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_STRINGIFY_FETCHES => false
    ];

    public array $options;

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
    public function __construct(
        public string $dsn,
        public ?string $username = null,
        #[SensitiveParameter]
        public ?string $password = null,
        array $options = []
    )
    {
        $this->options = self::DEFAULT_OPTIONS + $options;
    }

    /**
     * Creates a new PDO instance.
     *
     * @return PDO
     * @throws ConnectionException
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
            throw ConnectionException::of($err->getCode(), $err->getMessage(), $err);
        }
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __debugInfo(): ?array
    {
        return null;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public final function jsonSerialize(): null
    {
        return null;
    }

}
