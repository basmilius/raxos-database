<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use JetBrains\PhpStorm\Pure;
use Raxos\Foundation\PHP\MagicMethods\DebugInfoInterface;
use Raxos\Foundation\Util\ArrayUtil;
use function array_map;
use function http_build_query;
use function is_array;

/**
 * Class Cache
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 14-08-2024
 */
final class Cache implements CacheInterface, DebugInfoInterface
{

    private array $instances = [];

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 14-08-2024
     */
    #[Pure]
    public function find(string $modelClass, callable $predicate): ?Model
    {
        $instances = $this->instances[$modelClass] ?? [];

        return ArrayUtil::first($instances, $predicate);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 14-08-2024
     */
    public function flush(string $modelClass): void
    {
        $this->instances[$modelClass] = [];
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 14-08-2024
     */
    public function flushAll(): void
    {
        $this->instances = [];
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 14-08-2024
     */
    #[Pure]
    public function get(string $modelClass, array|string|int $primaryKey): ?Model
    {
        $key = $this->key($primaryKey);

        return $this->instances[$modelClass][$key] ?? null;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 14-08-2024
     */
    #[Pure]
    public function has(string $modelClass, array|string|int $primaryKey): bool
    {
        $key = $this->key($primaryKey);

        return isset($this->instances[$modelClass][$key]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 14-08-2024
     */
    public function set(string $modelClass, array|string|int $primaryKey, Model $instance): void
    {
        $key = $this->key($primaryKey);
        $this->instances[$modelClass][$key] = $instance;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 14-08-2024
     */
    public function unset(string $modelClass, array|string|int $primaryKey): void
    {
        $key = $this->key($primaryKey);
        unset($this->instances[$modelClass][$key]);
    }

    /**
     * Returns a string representation of the primary key.
     *
     * @param array|string|int $primaryKey
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 14-08-2024
     */
    private function key(array|string|int $primaryKey): string
    {
        if (!is_array($primaryKey)) {
            $primaryKey = [$primaryKey];
        }

        return http_build_query($primaryKey);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 14-08-2024
     */
    public function __debugInfo(): array
    {
        return array_map(static fn(array $instances) => array_map(strval(...), $instances), $this->instances);
    }

}
