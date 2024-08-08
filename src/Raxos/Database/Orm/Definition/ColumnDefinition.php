<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Definition;

use JetBrains\PhpStorm\ArrayShape;
use Raxos\Database\Orm\Attribute\RelationAttributeInterface;
use Raxos\Foundation\Collection\Arrayable;

/**
 * Class ColumnDefinition
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Definition
 * @since 1.0.0
 */
final readonly class ColumnDefinition implements Arrayable
{

    /**
     * ColumnDefinition constructor.
     *
     * @param string|null $alias
     * @param string|null $cast
     * @param mixed $default
     * @param bool $isImmutable
     * @param bool $isPrimary
     * @param bool $isForeign
     * @param bool $isHidden
     * @param bool $isVisible
     * @param string $key
     * @param string $name
     * @param RelationAttributeInterface|null $relation
     * @param array $types
     * @param string[]|null $hiddenOnly
     * @param string[]|null $visibleOnly
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
        public bool $isForeign,
        public bool $isHidden,
        public bool $isVisible,
        public string $name,
        public string $key,
        public ?RelationAttributeInterface $relation,
        public array $types,
        public ?array $hiddenOnly,
        public ?array $visibleOnly
    )
    {
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
        'is_foreign' => 'bool',
        'is_hidden' => 'bool',
        'is_visible' => 'bool',
        'name' => 'string',
        'key' => 'string',
        'relation' => '\Raxos\Database\Orm\Attribute\RelationAttribute|null',
        'types' => 'array',
        'hidden_only' => 'string[]|null',
        'visible_only' => 'string[]|null'
    ])]
    public function toArray(): array
    {
        return [
            'alias' => $this->alias,
            'cast' => $this->cast,
            'default' => $this->default,
            'is_immutable' => $this->isImmutable,
            'is_primary' => $this->isPrimary,
            'is_foreign' => $this->isForeign,
            'is_hidden' => $this->isHidden,
            'is_visible' => $this->isVisible,
            'name' => $this->name,
            'key' => $this->key,
            'relation' => $this->relation,
            'types' => $this->types,
            'hidden_only' => $this->hiddenOnly,
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
            $state['default'],
            $state['isImmutable'],
            $state['isPrimary'],
            $state['isForeign'],
            $state['isHidden'],
            $state['isVisible'],
            $state['name'],
            $state['key'],
            $state['relation'],
            $state['types'],
            $state['hidden_only'],
            $state['visible_only']
        );
    }

}
