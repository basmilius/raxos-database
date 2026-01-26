<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Definition;

use Closure;
use JetBrains\PhpStorm\ArrayShape;

/**
 * Class MacroDefinition
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Definition
 * @since 1.0.17
 */
final readonly class MacroDefinition extends PropertyDefinition
{

    /**
     * MacroDefinition constructor.
     *
     * @param Closure $callback
     * @param bool $isCached
     * @param string $name
     * @param string|null $alias
     * @param bool $isHidden
     * @param bool $isVisible
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public Closure $callback,
        public bool $isCached,
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
        'is_cached' => 'bool'
    ])]
    public function jsonSerialize(): array
    {
        return [
            ...parent::jsonSerialize(),
            'is_cached' => $this->isCached
        ];
    }

}
