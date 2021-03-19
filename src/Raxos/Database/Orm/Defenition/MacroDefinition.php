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

    public string $name;

    /**
     * MacroDefinition constructor.
     *
     * @param string|null $alias
     * @param bool $isCacheable
     * @param bool $isHidden
     * @param bool $isVisible
     * @param string $method
     * @param string $property
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        public ?string $alias,
        public bool $isCacheable,
        public bool $isHidden,
        public bool $isVisible,
        public string $method,
        public string $property
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
        'is_cacheable' => 'bool',
        'is_hidden' => 'bool',
        'is_visible' => 'bool',
        'method' => 'string',
        'property' => 'string'
    ])]
    public final function toArray(): array
    {
        return [
            'alias' => $this->alias,
            'is_cacheable' => $this->isCacheable,
            'is_hidden' => $this->isHidden,
            'is_visible' => $this->isVisible,
            'method' => $this->method,
            'property' => $this->property
        ];
    }

}
