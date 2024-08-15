<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Definition;

/**
 * Class MacroDefinition
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Definition
 * @since 13-08-2024
 */
final readonly class MacroDefinition extends PropertyDefinition
{

    /**
     * MacroDefinition constructor.
     *
     * @param (callable&string)|(callable&array) $callback
     * @param bool $isCached
     * @param string $name
     * @param string|null $alias
     * @param bool $isHidden
     * @param bool $isVisible
     *
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
     */
    public function __construct(
        public string|array $callback,
        public bool $isCached,
        string $name,
        ?string $alias,
        bool $isHidden = false,
        bool $isVisible = false
    )
    {
        parent::__construct($name, $alias, $isHidden, $isVisible);
    }

}
