<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use ArrayAccess;
use Raxos\Contract\Database\Orm\{BackboneInterface, ModelInterface, OrmExceptionInterface};
use Raxos\Contract\DebuggableInterface;
use Raxos\Database\Orm\Error\ReadonlyModelException;
use Raxos\Foundation\Access\{ArrayAccessible, ObjectAccessible};

/**
 * Class ReadonlyModel
 *
 * A read-only view on a {@see Model}, sharing its {@see Backbone} and therefore
 * its data, caches and relations. Safe to hand to untrusted consumers such as
 * template engines: it has no save, destroy, update or delete, and no magic
 * method call, so a template can never invoke ORM behaviour. Related models are
 * wrapped in a read-only view as well.
 *
 * Reading a relation still triggers lazy database loading and a macro still runs
 * its closure, identical to {@see Model::getValue()}; a read-only model guards
 * against mutation, not against read side effects.
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 3.1.0
 */
final class ReadonlyModel implements ArrayAccess, DebuggableInterface, ModelInterface
{

    use ArrayAccessible;
    use ObjectAccessible;
    use ModelReadable;

    /**
     * ReadonlyModel constructor.
     *
     * @param BackboneInterface $backbone
     * @param array $hidden
     * @param array $only
     * @param array $visible
     *
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    public function __construct(
        BackboneInterface $backbone,
        array $hidden = [],
        array $only = [],
        array $visible = []
    )
    {
        $this->backbone = $backbone;
        $this->hidden = $hidden;
        $this->only = $only;
        $this->visible = $visible;
    }

    /**
     * {@inheritdoc}
     *
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    public function readonly(): self
    {
        return $this;
    }

    /**
     * Blocks writing to a read-only model.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return void
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    public function setValue(string $key, mixed $value): void
    {
        throw ReadonlyModelException::forWrite($this->backbone->structure->class, $key);
    }

    /**
     * Blocks unsetting on a read-only model.
     *
     * @param string $key
     *
     * @return void
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    public function unsetValue(string $key): void
    {
        throw ReadonlyModelException::forUnset($this->backbone->structure->class, $key);
    }

    /**
     * Blocks calling methods that a read-only model does not have. A template
     * engine falls back to a method lookup when a name is not a property, so
     * without this an unknown or blocked name would silently render as empty.
     *
     * @param string $name
     * @param array $arguments
     *
     * @return never
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    public function __call(string $name, array $arguments): never
    {
        throw ReadonlyModelException::forCall($this->backbone->structure->class, $name);
    }

    /**
     * {@inheritdoc}
     *
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    protected function readValue(string $key): mixed
    {
        $value = $this->backbone->getValue($key);

        return match (true) {
            $value instanceof Model, $value instanceof ModelArrayList => $value->readonly(),
            default => $value
        };
    }

    /**
     * {@inheritdoc}
     *
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    protected function createVisibilityClone(): static
    {
        return new self($this->backbone);
    }

}
