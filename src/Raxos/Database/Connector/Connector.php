<?php
declare(strict_types=1);

namespace Raxos\Database\Connector;

use JetBrains\PhpStorm\Pure;
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
abstract class Connector implements DebugInfoInterface
{

    private const DEFAULT_OPTIONS = [
        PDO::ATTR_CASE => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_STRINGIFY_FETCHES => false
    ];

    private array $options;

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
        private string $dsn,
        private ?string $username = null,
        private ?string $password = null,
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
     * Gets the DSN.
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    public final function getDsn(): string
    {
        return $this->dsn;
    }

    /**
     * Gets the username.
     *
     * @return string|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    public final function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * Gets the password.
     *
     * @return string|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    public final function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Gets the options.
     *
     * @return array
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    public final function getOptions(): array
    {
        return $this->options;
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

}
