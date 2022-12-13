<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Definition;

use JetBrains\PhpStorm\ArrayShape;
use Raxos\Foundation\Collection\Arrayable;

/**
 * Class MacroDefinition
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Definition
 * @since 1.0.0
 */
final readonly class MacroDefinition implements Arrayable
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
            $state['isCacheable'],
            $state['isHidden'],
            $state['isVisible'],
            $state['method'],
            $state['property']
        );
    }

}
