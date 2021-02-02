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

    /**
     * FieldDefinition constructor.
     *
     * @param string|null $alias
     * @param string|null $cast
     * @param mixed $default
     * @param bool $isPrimary
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
        public bool $isPrimary,
        public string $property,
        public ?RelationAttribute $relation,
        public array $types
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
        'is_primary' => 'bool',
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
            'is_primary' => $this->isPrimary,
            'property' => $this->property,
            'relation' => $this->relation,
            'types' => $this->types
        ];
    }

}
