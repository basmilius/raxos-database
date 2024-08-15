<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Definition;

use JetBrains\PhpStorm\Pure;
use Raxos\Foundation\Util\ArrayUtil;

/**
 * Class PropertyDefinition
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Definition
 * @since 13-08-2024
 */
abstract readonly class PropertyDefinition
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
     * @since 13-08-2024
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
     * @since 15-08-2024
     */
    #[Pure]
    public function isIn(array $keys): bool
    {
        return ArrayUtil::in($keys, [$this->name, $this->alias]);
    }

}
