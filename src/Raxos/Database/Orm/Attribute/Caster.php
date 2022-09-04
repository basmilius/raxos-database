<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;

/**
 * Class Caster
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.0
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Caster
{

    /**
     * Caster constructor.
     *
     * @template C of \Raxos\Database\Orm\Cast\CastInterface
     *
     * @param class-string<C> $caster
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(public readonly string $caster)
    {
    }

}
