<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Defenition;

use JetBrains\PhpStorm\ArrayShape;
use Raxos\Database\Orm\Attribute\RelationAttribute;
use Raxos\Foundation\Collection\Arrayable;

/**
 * Class FieldDefinition
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Defenition
 * @since 1.0.0
 */
final class FieldDefinition implements Arrayable
{

    public readonly string $key;
    public readonly string $name;

    /**
     * FieldDefinition constructor.
     *
     * @param string|null $alias
     * @param string|null $cast
     * @param string|null $dataKey
     * @param mixed $default
     * @param bool $isImmutable
     * @param bool $isPrimary
     * @param bool $isHidden
     * @param bool $isVisible
     * @param string $property
     * @param RelationAttribute|null $relation
     * @param array $types
     * @param string[]|null $visibleOnly
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        public readonly ?string $alias,
        public readonly ?string $cast,
        public readonly ?string $dataKey,
        public readonly mixed $default,
        public readonly bool $isImmutable,
        public readonly bool $isPrimary,
        public readonly bool $isHidden,
        public readonly bool $isVisible,
        public readonly string $property,
        public readonly ?RelationAttribute $relation,
        public readonly array $types,
        public readonly ?array $visibleOnly
    )
    {
        $this->name = $alias ?? $property;
        $this->key = $dataKey ?? $this->name;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[ArrayShape([
        'alias' => 'string|null',
        'cast' => 'string|null',
        'data_key' => 'string|null',
        'default' => 'mixed',
        'is_immutable' => 'bool',
        'is_primary' => 'bool',
        'is_hidden' => 'bool',
        'is_visible' => 'bool',
        'property' => 'string',
        'relation' => '\Raxos\Database\Orm\Attribute\RelationAttribute|null',
        'types' => 'array',
        'visible_only' => 'string[]|null'
    ])]
    public final function toArray(): array
    {
        return [
            'alias' => $this->alias,
            'cast' => $this->cast,
            'data_key' => $this->default,
            'default' => $this->default,
            'is_immutable' => $this->isImmutable,
            'is_primary' => $this->isPrimary,
            'is_hidden' => $this->isHidden,
            'is_visible' => $this->isVisible,
            'property' => $this->property,
            'relation' => $this->relation,
            'types' => $this->types,
            'visible_only' => $this->visibleOnly
        ];
    }

    /**
     * Restores the state of the class from exported data.
     *
     * @param array $state
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function __set_state(array $state): self
    {
        return new self(
            $state['alias'],
            $state['cast'],
            $state['data_key'],
            $state['default'],
            $state['isImmutable'],
            $state['isPrimary'],
            $state['isHidden'],
            $state['isVisible'],
            $state['property'],
            $state['relation'],
            $state['types'],
            $state['visible_only']
        );
    }

}
