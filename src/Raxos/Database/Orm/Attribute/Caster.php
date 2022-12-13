<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use Raxos\Database\Orm\Cast\CastInterface;

/**
 * Class Caster
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.0
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Caster
{

    /**
     * Caster constructor.
     *
     * @template C of CastInterface
     *
     * @param class-string<C> $caster
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(public string $caster)
    {
    }

}
