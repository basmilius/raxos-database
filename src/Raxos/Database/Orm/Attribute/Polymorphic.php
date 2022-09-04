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
    public function __construct(
        public readonly string $column = 'type',
        public readonly array $map = []
    )
    {
    }

}
