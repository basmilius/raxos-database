<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use Raxos\Contract\ProxyInterface;
use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Database\Orm\Error\ReadonlyProxyException;
use Traversable;
use function array_map;

/**
 * Class ModelArrayListProxy
 *
 * A read-only proxy around a {@see ModelArrayList}, safe to hand to untrusted
 * consumers such as template engines. Iterating or indexing yields a
 * {@see ModelProxy} per item and every mutating collection method is blocked.
 *
 * @template TKey of array-key
 * @template TValue of Model
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 2.4.0
 */
final readonly class ModelArrayListProxy implements ArrayAccess, Countable, IteratorAggregate, ProxyInterface
{

    /**
     * ModelArrayListProxy constructor.
     *
     * @param ModelArrayList<TKey, TValue> $list
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.4.0
     */
    public function __construct(
        private ModelArrayList $list
    ) {}

    /**
     * {@inheritdoc}
     * @return Traversable<TKey, ModelProxy>
     * @author Bas Milius <bas@mili.us>
     * @since 2.4.0
     */
    public function getIterator(): Traversable
    {
        foreach ($this->list as $key => $model) {
            yield $key => $model->proxy();
        }
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.4.0
     */
    public function count(): int
    {
        return $this->list->count();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.4.0
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->list[$offset]);
    }

    /**
     * {@inheritdoc}
     * @return ModelProxy|null
     * @author Bas Milius <bas@mili.us>
     * @since 2.4.0
     */
    public function offsetGet(mixed $offset): ?ModelProxy
    {
        return $this->list[$offset]?->proxy();
    }

    /**
     * {@inheritdoc}
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.4.0
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw ReadonlyProxyException::forWrite(self::class, (string)$offset);
    }

    /**
     * {@inheritdoc}
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.4.0
     */
    public function offsetUnset(mixed $offset): void
    {
        throw ReadonlyProxyException::forWrite(self::class, (string)$offset);
    }

    /**
     * {@inheritdoc}
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.4.0
     */
    public function toArray(): array
    {
        return array_map(static fn(Model $model): array => $model->toArray(), $this->list->toArray());
    }

    /**
     * {@inheritdoc}
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.4.0
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

}
