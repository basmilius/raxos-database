<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use JetBrains\PhpStorm\Pure;
use Raxos\Database\Error\DatabaseException;
use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function implode;
use function is_scalar;

/**
 * Class Cache
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.0
 */
class Cache
{

    /** @var Model[][] */
    private array $instances = [];

    /**
     * Flushes the cache. When a model class is given, only those models
     * are flushed from cache.
     *
     * @param class-string<Model>|null $modelClass
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function flush(?string $modelClass = null): void
    {
        if ($modelClass !== null) {
            $this->instances[$modelClass] = [];
        } else {
            $this->instances = [];
        }
    }

    /**
     * Gets a cached model by its primary key.
     *
     * @param class-string<Model> $modelClass
     * @param array|string|int $key
     *
     * @return Model|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    public function get(string $modelClass, array|string|int $key): ?Model
    {
        $key = $this->key($key);

        return $this->instances[$modelClass][$key] ?? null;
    }

    /**
     * Gets multiple cached models by their primary keys.
     *
     * @param class-string<Model> $modelClass
     * @param array $keys
     *
     * @return Model[]
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function getAll(string $modelClass, array $keys): array
    {
        $keys = array_map(fn(array|string|int $key) => $this->key($key), $keys);
        $results = array_map(fn(string $key) => $this->get($modelClass, $key), $keys);
        $results = array_filter($results, fn(?Model $model) => $model !== null);
        $results = array_values($results);

        return $results;
    }

    /**
     * Returns TRUE if there is a model cached with the given primary key.
     *
     * @param class-string<Model> $modelClass
     * @param array|string|int $key
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    public function has(string $modelClass, array|string|int $key): bool
    {
        $key = $this->key($key);

        return isset($this->instances[$modelClass][$key]);
    }

    /**
     * Creates a scalar string key of the given key.
     *
     * @param array|string|int $key
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    public function key(array|string|int $key): string
    {
        if (is_scalar($key)) {
            return (string)$key;
        }

        return implode('|', $key);
    }

    /**
     * Gets the cached keys for the given model.
     *
     * @param class-string<Model> $modelClass
     *
     * @return string[]
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    public function keys(string $modelClass): array
    {
        return array_keys($this->instances[$modelClass] ?? []);
    }

    /**
     * Removes the given model from cache.
     *
     * @param Model $model
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function remove(Model $model): void
    {
        $key = $this->key($model->getPrimaryKeyValues());

        unset($this->instances[$model::class][$key]);
    }

    /**
     * Removes a model with the given class and key from cache.
     *
     * @param class-string<Model> $modelClass
     * @param array|string|int $key
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function removeByKey(string $modelClass, array|string|int $key): void
    {
        $key = $this->key($key);

        unset($this->instances[$modelClass][$key]);
    }

    /**
     * Caches the given model.
     *
     * @param Model $model
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function set(Model $model): void
    {
        $key = $this->key($model->getPrimaryKeyValues());

        $this->instances[$model::class][$key] = $model;
    }

}
