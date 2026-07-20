<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use ArrayAccess;
use Raxos\Contract\{DebuggableInterface, ProxyableInterface, ProxyInterface};
use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Database\Orm\Error\ReadonlyProxyException;
use Stringable;

/**
 * Class ModelProxy
 *
 * A read-only proxy around a {@see Model}, safe to hand to untrusted consumers
 * such as template engines. It exposes the model's columns, relations, macros
 * and embedded values by name or alias, but blocks every mutation
 * ({@see Model::save()}, {@see Model::destroy()}, property writes) and method
 * call so a template can never invoke ORM behaviour. A key that does not map to
 * an existing property resolves to NULL. Related {@see Model} and
 * {@see ModelArrayList} values are wrapped in a proxy recursively.
 *
 * Reading a relation still triggers lazy database loading and a macro still runs
 * its closure, identical to {@see Model::getValue()}; the proxy guards against
 * mutation, not against read side effects.
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 2.4.0
 */
final readonly class ModelProxy implements ArrayAccess, DebuggableInterface, ProxyInterface, Stringable
{

    /**
     * ModelProxy constructor.
     *
     * @param Model $model
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.4.0
     */
    public function __construct(
        private Model $model
    ) {}

    /**
     * Gets the value at the given offset, or NULL when the offset does not map
     * to an existing property.
     *
     * @param string $name
     *
     * @return mixed
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.4.0
     */
    public function __get(string $name): mixed
    {
        return $this->read($name);
    }

    /**
     * Returns TRUE if the given offset maps to an existing property.
     *
     * @param string $name
     *
     * @return bool
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.4.0
     */
    public function __isset(string $name): bool
    {
        return $this->model->hasValue($name);
    }

    /**
     * Blocks writing through the proxy.
     *
     * @param string $name
     * @param mixed $value
     *
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.4.0
     */
    public function __set(string $name, mixed $value): void
    {
        throw ReadonlyProxyException::forWrite(self::class, $name);
    }

    /**
     * Blocks unsetting through the proxy.
     *
     * @param string $name
     *
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.4.0
     */
    public function __unset(string $name): void
    {
        throw ReadonlyProxyException::forWrite(self::class, $name);
    }

    /**
     * Blocks calling methods through the proxy.
     *
     * @param string $name
     * @param array $arguments
     *
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.4.0
     */
    public function __call(string $name, array $arguments): never
    {
        throw ReadonlyProxyException::forCall(self::class, $name);
    }

    /**
     * {@inheritdoc}
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.4.0
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->model->hasValue((string)$offset);
    }

    /**
     * {@inheritdoc}
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.4.0
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->read((string)$offset);
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
        return $this->model->toArray();
    }

    /**
     * {@inheritdoc}
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.4.0
     */
    public function jsonSerialize(): array
    {
        return $this->model->toArray();
    }

    /**
     * {@inheritdoc}
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.4.0
     */
    public function __debugInfo(): array
    {
        return $this->model->toArray();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.4.0
     */
    public function __toString(): string
    {
        return (string)$this->model;
    }

    /**
     * Reads an existing property from the model and wraps related models in a
     * proxy. Returns NULL when the key does not map to a property.
     *
     * @param string $name
     *
     * @return mixed
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 2.4.0
     */
    private function read(string $name): mixed
    {
        if (!$this->model->hasValue($name)) {
            return null;
        }

        $value = $this->model->getValue($name);

        if ($value instanceof ProxyableInterface) {
            return $value->proxy();
        }

        return $value;
    }

}
