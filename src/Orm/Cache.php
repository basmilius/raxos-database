<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use Raxos\Contract\Database\Orm\CacheInterface;
use Raxos\Contract\DebuggableInterface;
use Raxos\Foundation\Util\ArrayUtil;
use function array_first;
use function array_key_first;
use function array_map;
use function array_pop;
use function count;
use function is_int;
use function is_string;
use function json_encode;
use function ksort;

/**
 * Class Cache
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.17
 */
final class Cache implements CacheInterface, DebuggableInterface
{
    /**
     * Retains shared instances until their owning cache or scope releases them.
     *
     * @var array
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    private array $instances = [];

    /**
     * Exposes the number of cached identities without rebuilding an instance list.
     *
     * @var int
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    private int $size = 0;

    /**
     * Records identities introduced by each scope so cleanup leaves earlier cached models intact.
     *
     * @var array<array<class-string, array<array-key, true>>>
     * @author Bas Milius <bas@mili.us>
     * @since 3.2.0
     */
    private array $scopes = [];

    /**
     * Cache constructor.
     *
     * @param int $maxSize
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.3.0
     */
    public function __construct(
        public readonly int $maxSize = 0
    )
    {
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function find(
        string $modelClass,
        callable $predicate
    ): ?Model
    {
        $instances = $this->instances[$modelClass] ?? [];

        return ArrayUtil::first($instances, $predicate);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function flush(string $modelClass): void
    {
        $this->size -= count($this->instances[$modelClass] ?? []);
        $this->instances[$modelClass] = [];
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function flushAll(): void
    {
        $this->instances = [];
        $this->size = 0;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function get(
        string $modelClass,
        array|string|int $primaryKey
    ): ?Model
    {
        $key = $this->key($primaryKey);

        return $this->instances[$modelClass][$key] ?? null;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function has(
        string $modelClass,
        array|string|int $primaryKey
    ): bool
    {
        $key = $this->key($primaryKey);

        return isset($this->instances[$modelClass][$key]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function set(
        string $modelClass,
        array|string|int $primaryKey,
        Model $instance
    ): void
    {
        $key = $this->key($primaryKey);

        if (!isset($this->instances[$modelClass][$key])) {
            ++$this->size;

            foreach ($this->scopes as &$scope) {
                $scope[$modelClass][$key] = true;
            }

            unset($scope);
        }

        $this->instances[$modelClass][$key] = $instance;

        if ($this->scopes === [] && $this->maxSize > 0 && $this->size > $this->maxSize) {
            $this->evict();
        }
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function unset(
        string $modelClass,
        array|string|int $primaryKey
    ): void
    {
        $key = $this->key($primaryKey);

        if (isset($this->instances[$modelClass][$key])) {
            --$this->size;
        }

        unset($this->instances[$modelClass][$key]);
    }

    /**
     * Removes an identity without invalidating model objects already held by callers.
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 2.3.0
     */
    private function evict(): void
    {
        while ($this->size > $this->maxSize) {
            $evicted = false;

            foreach ($this->instances as $modelClass => $entries) {
                if (empty($entries)) {
                    continue;
                }

                unset($this->instances[$modelClass][array_key_first($this->instances[$modelClass])]);
                --$this->size;
                $evicted = true;

                if ($this->size <= $this->maxSize) {
                    return;
                }
            }

            // Safeguard: if `$size` and the actual contents drift apart for
            // any reason, bail out instead of looping forever.
            if (!$evicted) {
                $this->size = 0;

                return;
            }
        }
    }

    /**
     * Returns a string representation of the primary key.
     *
     * @param array|string|int $primaryKey
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    private function key(array|string|int $primaryKey): string
    {
        if (is_int($primaryKey)) {
            return (string)$primaryKey;
        }

        if (is_string($primaryKey)) {
            return $primaryKey;
        }

        if (count($primaryKey) === 1) {
            $value = array_first($primaryKey);

            if (is_int($value)) {
                return (string)$value;
            }

            if (is_string($value)) {
                return $value;
            }
        }

        // note(Bas): for composite primary keys, sort by key so callers
        //  passing the same logical key in different orders still hit the
        //  same cache slot.
        ksort($primaryKey);

        return json_encode($primaryKey);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __debugInfo(): array
    {
        return array_map(static fn(array $instances) => array_map(strval(...), $instances), $this->instances);
    }

    /**
     * Keeps existing identities, releasing only models first loaded by this callback.
     *
     * @template T
     * @param callable():T $fn
     * @return T
     * @author Bas Milius <bas@mili.us>
     * @since 3.2.0
     */
    public function scope(callable $fn): mixed
    {
        $this->scopes[] = [];

        try {
            return $fn();
        } finally {
            foreach (array_pop($this->scopes) as $class => $keys) {
                foreach ($keys as $key => $_) {
                    $this->unset($class, $key);
                }
            }
        }
    }
}
