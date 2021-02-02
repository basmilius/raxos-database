<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;

/**
 * Class Macro
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.0
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Macro
{

    /**
     * Macro constructor.
     *
     * @param string|null $alias
     * @param bool $isCacheable
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        private ?string $alias = null,
        private bool $isCacheable = false
    )
    {
    }

    /**
     * Gets the alias.
     *
     * @return string|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getAlias(): ?string
    {
        return $this->alias;
    }

    /**
     * Gets if the macro is cacheable..
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function isCacheable(): bool
    {
        return $this->isCacheable;
    }

}
