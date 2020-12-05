<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;

/**
 * Class Column
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.0
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Column
{

    /**
     * Column constructor.
     *
     * @param string|null $alias
     * @param string|int|float|null $default
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(private ?string $alias = null, private string|int|float|null $default = null)
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
     * Gets the default value.
     *
     * @return string|int|float|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getDefault(): string|int|float|null
    {
        return $this->default;
    }

}
