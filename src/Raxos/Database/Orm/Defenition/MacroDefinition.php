<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Defenition;

use JetBrains\PhpStorm\ArrayShape;
use Raxos\Foundation\Collection\Arrayable;

/**
 * Class MacroDefinition
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Defenition
 * @since 1.0.0
 */
final class MacroDefinition implements Arrayable
{

    /**
     * MacroDefinition constructor.
     *
     * @param string|null $alias
     * @param bool $isCacheable
     * @param string $method
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        public ?string $alias,
        public bool $isCacheable,
        public string $method
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
        'is_cacheable' => 'bool',
        'method' => 'string'
    ])]
    public final function toArray(): array
    {
        return [
            'alias' => $this->alias,
            'is_cacheable' => $this->isCacheable,
            'method' => $this->method
        ];
    }

}
