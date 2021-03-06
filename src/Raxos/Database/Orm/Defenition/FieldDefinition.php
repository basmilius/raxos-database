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

    public string $name;

    /**
     * FieldDefinition constructor.
     *
     * @param string|null $alias
     * @param string|null $cast
     * @param mixed $default
     * @param bool $isImmutable
     * @param bool $isPrimary
     * @param bool $isHidden
     * @param bool $isVisible
     * @param string $property
     * @param RelationAttribute|null $relation
     * @param array $types
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        public ?string $alias,
        public ?string $cast,
        public mixed $default,
        public bool $isImmutable,
        public bool $isPrimary,
        public bool $isHidden,
        public bool $isVisible,
        public string $property,
        public ?RelationAttribute $relation,
        public array $types
    )
    {
        $this->name = $alias ?? $property;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[ArrayShape([
        'alias' => 'string|null',
        'cast' => 'string|null',
        'default' => 'mixed',
        'is_immutable' => 'bool',
        'is_primary' => 'bool',
        'is_hidden' => 'bool',
        'is_visible' => 'bool',
        'property' => 'string',
        'relation' => '\Raxos\Database\Orm\Attribute\RelationAttribute|null',
        'types' => 'array'
    ])]
    public final function toArray(): array
    {
        return [
            'alias' => $this->alias,
            'cast' => $this->cast,
            'default' => $this->default,
            'is_immutable' => $this->isImmutable,
            'is_primary' => $this->isPrimary,
            'is_hidden' => $this->isHidden,
            'is_visible' => $this->isVisible,
            'property' => $this->property,
            'relation' => $this->relation,
            'types' => $this->types
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
            $state['default'],
            $state['isImmutable'],
            $state['isPrimary'],
            $state['isHidden'],
            $state['isVisible'],
            $state['property'],
            $state['relation'],
            $state['types']
        );
    }

}
