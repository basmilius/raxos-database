<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Structure;

use Raxos\Database\Orm\Contract\StructureInterface;
use Raxos\Database\Orm\Definition\{ColumnDefinition, MacroDefinition, PropertyDefinition, RelationDefinition};
use Raxos\Database\Orm\Error\StructureException;
use Raxos\Database\Orm\Model;
use Raxos\Foundation\Contract\ArrayListInterface;
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
            $property instanceof ColumnDefinition => !$property->isHidden && !$forceHidden || $forceVisible,
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
     * @throws StructureException
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
                $result[$normalizeKey($key)] = self::normalizeKeys($value, $structure);
            }
        }

        return $result;
    }

}
