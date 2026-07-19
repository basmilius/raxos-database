<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Structure;

use Raxos\Contract\Collection\ArrayListInterface;
use Raxos\Contract\Database\Orm\{OrmExceptionInterface, StructureInterface};
use Raxos\Database\Orm\Definition\{ColumnDefinition, EmbeddedDefinition, MacroDefinition, PropertyDefinition, RelationDefinition};
use Raxos\Database\Orm\Model;
use function is_int;
use function is_string;

/**
 * Class StructureHelper
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Structure
 * @since 1.0.17
 */
final class StructureHelper
{

    /**
     * Groups the given instances by their model. If a parent model
     * is given, that model is filtered out because it probably is
     * the parent model within a polymorphic structure.
     *
     * @param ArrayListInterface $instances
     * @param string|null $parentClass
     *
     * @return ArrayListInterface<class-string<Model>, ArrayListInterface>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function groupInstances(ArrayListInterface $instances, ?string $parentClass = null): ArrayListInterface
    {
        return $instances
            ->filter(static fn(Model $instance) => $instance::class !== $parentClass)
            ->groupBy(static fn(Model $instance) => $instance::class);
    }

    /**
     * Returns TRUE if the given property should be visible by default.
     *
     * @param PropertyDefinition $property
     * @param bool $forceVisible
     * @param bool $forceHidden
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function isVisible(PropertyDefinition $property, bool $forceVisible, bool $forceHidden): bool
    {
        return match (true) {
            $property instanceof ColumnDefinition, $property instanceof EmbeddedDefinition => !$property->isHidden && !$forceHidden || $forceVisible,
            $property instanceof MacroDefinition, $property instanceof RelationDefinition => $property->isVisible && !$forceHidden || $forceVisible
        };
    }

    /**
     * Returns a normalized array for use in visibility.
     *
     * @param string[]|string $keys
     * @param StructureInterface|null $structure
     *
     * @return string[]
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function normalizeKeys(array|string $keys, ?StructureInterface $structure = null): array
    {
        $normalizeKey = static fn(string $key) => $structure?->getProperty($key)->name ?? $key;

        if (is_string($keys)) {
            return [$normalizeKey($keys) => null];
        }

        $result = [];

        foreach ($keys as $key => $value) {
            if (is_int($key)) {
                $result[$normalizeKey($value)] = null;
            } elseif ($value === null) {
                $result[$normalizeKey($key)] = null;
            } else {
                // note: nested sub-maps address a related model, so they must not be
                // resolved against this structure; they are re-normalized against the
                // relation's own structure when applied to it during serialization.
                $result[$normalizeKey($key)] = self::normalizeKeys($value, null);
            }
        }

        return $result;
    }

}
