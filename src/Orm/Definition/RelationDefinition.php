<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Definition;

use JetBrains\PhpStorm\ArrayShape;
use Raxos\Database\Orm\Contract\RelationAttributeInterface;

/**
 * Class RelationDefinition
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Definition
 * @since 1.0.17
 */
final readonly class RelationDefinition extends PropertyDefinition
{

    /**
     * RelationDefinition constructor.
     *
     * @param RelationAttributeInterface $relation
     * @param string[] $types
     * @param array|null $visibleOnly
     * @param string $name
     * @param string|null $alias
     * @param bool $isHidden
     * @param bool $isVisible
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public RelationAttributeInterface $relation,
        public array $types,
        public ?array $visibleOnly,
        string $name,
        ?string $alias,
        bool $isHidden = false,
        bool $isVisible = false
    )
    {
        parent::__construct($name, $alias, $isHidden, $isVisible);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    #[ArrayShape([
        'name' => 'string',
        'alias' => 'string|null',
        'is_hidden' => 'bool',
        'is_visible' => 'bool',
        'relation' => RelationAttributeInterface::class,
        'types' => 'string[]',
        'visible_only' => 'string[]'
    ])]
    public function jsonSerialize(): array
    {
        return [
            ...parent::jsonSerialize(),
            'relation' => $this->relation,
            'types' => $this->types,
            'visible_only' => $this->visibleOnly
        ];
    }

}
