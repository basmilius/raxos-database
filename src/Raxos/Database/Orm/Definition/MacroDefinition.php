<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Definition;

use JetBrains\PhpStorm\ArrayShape;
use Raxos\Database\Orm\Model;
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

    public string $key;

    /**
     * MacroDefinition constructor.
     *
     * @param string $name
     * @param string|null $alias
     * @param bool $isCacheable
     * @param bool $isHidden
     * @param bool $isVisible
     * @param (callable&string)|(callable&array) $callable
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        public string $name,
        public ?string $alias,
        public bool $isCacheable,
        public bool $isHidden,
        public bool $isVisible,
        public string|array $callable
    )
    {
        $this->key = $this->name;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[ArrayShape([
        'name' => 'string',
        'alias' => 'string|null',
        'is_cacheable' => 'bool',
        'is_hidden' => 'bool',
        'is_visible' => 'bool',
        'callable' => 'string'
    ])]
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'alias' => $this->alias,
            'is_cacheable' => $this->isCacheable,
            'is_hidden' => $this->isHidden,
            'is_visible' => $this->isVisible,
            'callable' => $this->callable
        ];
    }

    /**
     * Calls the macro.
     *
     * @template TModel of Model
     *
     * @param TModel&Model $modelInstance
     *
     * @return mixed
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function __invoke(Model $modelInstance): mixed
    {
        $callable = $this->callable;

        return $callable($modelInstance);
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
            $state['name'],
            $state['alias'],
            $state['isCacheable'],
            $state['isHidden'],
            $state['isVisible'],
            $state['callable']
        );
    }

}
