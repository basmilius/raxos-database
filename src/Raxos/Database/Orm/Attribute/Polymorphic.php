<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;

/**
 * Class Polymorphic
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.0
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Polymorphic
{

    /**
     * Polymorphic constructor.
     *
     * @param string $column
     * @param array $map
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(private string $column = 'type', private array $map = [])
    {
    }

    /**
     * Gets the column to check.
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getColumn(): string
    {
        return $this->column;
    }

    /**
     * Gets the class map for available types.
     *
     * @return array
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getMap(): array
    {
        return $this->map;
    }

}
