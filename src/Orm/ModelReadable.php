<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use Raxos\Contract\Database\Orm\BackboneInterface;
use Raxos\Contract\Database\Orm\ModelInterface;
use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Database\Orm\Definition\EmbeddedDefinition;
use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Database\Orm\Structure\StructureHelper;
use function array_diff_key;
use function array_key_exists;
use function array_merge_recursive;
use function implode;
use function is_array;
use function sprintf;

/**
 * Trait ModelReadable
 *
 * The read-only half of a model, shared by {@see Model} and {@see ReadonlyModel}.
 * Every method reads through the backbone, so instances that share a backbone
 * share their data, caches and modified state while keeping their own
 * visibility overrides.
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 3.1.0
 */
trait ModelReadable
{

    /**
     * Shares loaded values, changes and relations between views of the same model row.
     *
     * @var BackboneInterface
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    public readonly BackboneInterface $backbone;

    /**
     * Excludes selected properties when serializing this model view.
     *
     * @var array
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    private array $hidden = [];

    /**
     * Restricts serialization to selected properties when an explicit projection is present.
     *
     * @var array
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    private array $only = [];

    /**
     * Allows selected properties to override the structure's default hidden projection.
     *
     * @var array
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    private array $visible = [];

    /**
     * {@inheritdoc}
     *
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    public function makeHidden(array|string $keys): static
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $keys = StructureHelper::normalizeKeys($keys, $this->backbone->structure);

        $clone = $this->createVisibilityClone();
        $clone->hidden = array_merge_recursive($this->hidden, $keys);
        $clone->visible = array_diff_key($this->visible, $clone->hidden);
        $clone->only = $this->only;

        return $clone;
    }

    /**
     * {@inheritdoc}
     *
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    public function makeVisible(array|string $keys): static
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $keys = StructureHelper::normalizeKeys($keys, $this->backbone->structure);

        $clone = $this->createVisibilityClone();
        $clone->visible = array_merge_recursive($this->visible, $keys);
        $clone->hidden = array_diff_key($this->hidden, $clone->visible);
        $clone->only = $this->only;

        return $clone;
    }

    /**
     * {@inheritdoc}
     *
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    public function only(array|string $keys): static
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $keys = StructureHelper::normalizeKeys($keys, $this->backbone->structure);

        $hidden = [];
        $only = [];
        $visible = [];

        foreach ($this->backbone->structure->properties as $property) {
            if (array_key_exists($property->name, $keys)) {
                // note: keep the property visible via the marker, but record a nested
                // sub-map in $only so it narrows the relation (restrictive), unlike a
                // nested makeVisible which reveals additively.
                $visible[$property->name] = null;

                if (is_array($keys[$property->name])) {
                    $only[$property->name] = $keys[$property->name];
                }
            } else {
                $hidden[$property->name] = null;
            }
        }

        $clone = $this->createVisibilityClone();
        $clone->hidden = $hidden;
        $clone->only = $only;
        $clone->visible = $visible;

        return $clone;
    }

    /**
     * {@inheritdoc}
     *
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    public function getValue(string $key): mixed
    {
        $previous = $this->backbone->currentInstance;
        $this->backbone->currentInstance = $this;

        try {
            return $this->readValue($key);
        } finally {
            $this->backbone->currentInstance = $previous;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    public function hasValue(string $key): bool
    {
        $previous = $this->backbone->currentInstance;
        $this->backbone->currentInstance = $this;

        try {
            return $this->backbone->hasValue($key);
        } finally {
            $this->backbone->currentInstance = $previous;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * {@inheritdoc}
     *
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    public function toArray(): array
    {
        $result = [];
        $previous = $this->backbone->currentInstance;
        $this->backbone->currentInstance = $this;
        $noVisibilityOverrides = empty($this->visible) && empty($this->hidden);

        try {
            foreach ($this->backbone->structure->properties as $property) {
                $key = $property->alias ?? $property->name;

                if ($noVisibilityOverrides) {
                    $visible = StructureHelper::isVisible($property, false, false);
                } else {
                    $visible = StructureHelper::isVisible(
                        $property,
                        array_key_exists($property->name, $this->visible),
                        array_key_exists($property->name, $this->hidden)
                    );
                }

                if (!$visible) {
                    continue;
                }

                if ($property instanceof EmbeddedDefinition) {
                    $value = $this->readValue($property->name);
                    $result[$key] = $value !== null ? self::embeddedToArray($property, $value) : null;

                    continue;
                }

                $whitelist = $property instanceof RelationDefinition ? $property->visibleOnly : null;
                $nestedOnly = $this->only[$property->name] ?? null;
                $nestedVisible = $this->visible[$property->name] ?? null;
                $nestedHidden = $this->hidden[$property->name] ?? null;
                $value = $this->readValue($property->name);

                // note: a #[Visible([...])] whitelist and a nested only() both narrow the
                // relation (restrictive); a nested makeVisible/makeHidden reveals or hides
                // individual fields on top of the relation's defaults. Applied in that order.
                if ($whitelist !== null || is_array($nestedOnly) || is_array($nestedVisible) || is_array($nestedHidden)) {
                    $apply = static function (ModelInterface $model) use ($whitelist, $nestedOnly, $nestedVisible, $nestedHidden): array {
                        if ($whitelist !== null) {
                            $model = $model->only($whitelist);
                        }

                        if (is_array($nestedOnly)) {
                            $model = $model->only($nestedOnly);
                        }

                        if (is_array($nestedVisible)) {
                            $model = $model->makeVisible($nestedVisible);
                        }

                        if (is_array($nestedHidden)) {
                            $model = $model->makeHidden($nestedHidden);
                        }

                        return $model->toArray();
                    };

                    if ($value instanceof ModelInterface) {
                        $value = $apply($value);
                    } elseif ($value instanceof ModelArrayList) {
                        $value = $value->map($apply);
                    }
                }

                $result[$key] = $value;
            }
        } finally {
            $this->backbone->currentInstance = $previous;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    public function __debugInfo(): array
    {
        return $this->toArray();
    }

    /**
     * {@inheritdoc}
     *
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    public function __toString(): string
    {
        $values = $this->backbone->getPrimaryKeyValues();

        if ($values === null) {
            return sprintf('%s(no_pk)', $this->backbone->structure->class);
        }

        return sprintf('%s(%s)', $this->backbone->structure->class, implode(', ', $values));
    }

    /**
     * Reads the value at the given key from the backbone.
     *
     * @param string $key
     *
     * @return mixed
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    protected function readValue(string $key): mixed
    {
        return $this->backbone->getValue($key);
    }

    /**
     * Returns a new instance over the same backbone, used to apply visibility
     * overrides without touching the current instance.
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    abstract protected function createVisibilityClone(): static;

    /**
     * Converts an embedded value object to an associative array.
     *
     * @param EmbeddedDefinition $definition
     * @param object $value
     *
     * @return array
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    private static function embeddedToArray(
        EmbeddedDefinition $definition,
        object $value
    ): array
    {
        $result = [];

        foreach ($definition->columns as $column) {
            $columnKey = $column->alias ?? $column->name;
            $result[$columnKey] = $value->{$column->name};
        }

        foreach ($definition->embeddeds as $nested) {
            $nestedKey = $nested->alias ?? $nested->name;
            $nestedValue = $value->{$nested->name};
            $result[$nestedKey] = $nestedValue !== null ? self::embeddedToArray($nested, $nestedValue) : null;
        }

        return $result;
    }

}
