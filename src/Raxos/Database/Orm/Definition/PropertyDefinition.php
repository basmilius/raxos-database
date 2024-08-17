<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Definition;

use JetBrains\PhpStorm\{ArrayShape, Pure};
use JsonSerializable;
use Raxos\Foundation\Util\ArrayUtil;

/**
 * Class PropertyDefinition
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Definition
 * @since 1.0.17
 */
abstract readonly class PropertyDefinition implements JsonSerializable
{

    /**
     * PropertyDefinition constructor.
     *
     * @param string $name
     * @param string|null $alias
     * @param bool $isHidden
     * @param bool $isVisible
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public string $name,
        public ?string $alias,
        public bool $isHidden = false,
        public bool $isVisible = false
    ) {}

    /**
     * Returns true if the property is in the given array.
     *
     * @param string[] $keys
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    #[Pure]
    public function isIn(array $keys): bool
    {
        return ArrayUtil::in($keys, [$this->name, $this->alias]);
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
        'is_visible' => 'bool'
    ])]
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'alias' => $this->alias,
            'is_hidden' => $this->isHidden,
            'is_visible' => $this->isVisible
        ];
    }

}
