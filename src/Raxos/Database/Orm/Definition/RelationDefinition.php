<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Definition;

use Raxos\Database\Orm\Attribute\RelationAttributeInterface;

/**
 * Class RelationDefinition
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Definition
 * @since 13-08-2024
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
     * @since 13-08-2024
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

}
