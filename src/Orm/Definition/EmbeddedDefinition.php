<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Definition;

use JetBrains\PhpStorm\{ArrayShape, Pure};
use Raxos\Foundation\Util\ArrayUtil;

/**
 * Class EmbeddedDefinition
 *
 * Describes an embedded value object property on a model. Contains the
 * expanded column definitions (with prefix applied) and any nested
 * embedded definitions.
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Definition
 * @since 2.2.0
 */
final readonly class EmbeddedDefinition extends PropertyDefinition
{

    /**
     * EmbeddedDefinition constructor.
     *
     * @param class-string $embeddableClass
     * @param string $prefix
     * @param ColumnDefinition[] $columns
     * @param EmbeddedDefinition[] $embeddeds
     * @param bool $nullable
     * @param string[] $types
     * @param string $name
     * @param string|null $alias
     * @param bool $isHidden
     * @param bool $isVisible
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.2.0
     */
    public function __construct(
        public string $embeddableClass,
        public string $prefix,
        public array $columns,
        public array $embeddeds,
        public bool $nullable,
        public array $types,
        string $name,
        ?string $alias,
        bool $isHidden = false,
        bool $isVisible = false
    )
    {
        parent::__construct($name, $alias, $isHidden, $isVisible);
    }

    /**
     * Returns all leaf-level column definitions, flattened across
     * any nested embedded definitions.
     *
     * @return ColumnDefinition[]
     * @author Bas Milius <bas@mili.us>
     * @since 2.2.0
     */
    public function allColumns(): array
    {
        $all = $this->columns;

        foreach ($this->embeddeds as $nested) {
            $all = [...$all, ...$nested->allColumns()];
        }

        return $all;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.2.0
     */
    #[Pure]
    public function isIn(array $keys): bool
    {
        return ArrayUtil::in($keys, [$this->name, $this->alias]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.2.0
     */
    #[ArrayShape([
        'name' => 'string',
        'alias' => 'string|null',
        'is_hidden' => 'bool',
        'is_visible' => 'bool',
        'embeddable_class' => 'string',
        'prefix' => 'string',
        'nullable' => 'bool',
        'columns' => 'array',
        'embeddeds' => 'array'
    ])]
    public function jsonSerialize(): array
    {
        return [
            ...parent::jsonSerialize(),
            'embeddable_class' => $this->embeddableClass,
            'prefix' => $this->prefix,
            'nullable' => $this->nullable,
            'columns' => $this->columns,
            'embeddeds' => $this->embeddeds
        ];
    }

}
