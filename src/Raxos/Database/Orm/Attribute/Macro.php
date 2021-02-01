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
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Macro
{

    /**
     * Macro constructor.
     *
     * @param string|null $name
     * @param bool $isCacheable
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(private ?string $name = null, private bool $isCacheable = false)
    {
    }

    /**
     * Gets the name of the macro.
     *
     * @return string|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Returns TRUE if the macro is cacheable.
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
