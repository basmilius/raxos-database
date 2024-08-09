<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use JetBrains\PhpStorm\Pure;

/**
 * Class Visible
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.0
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Visible implements AttributeInterface
{

    /**
     * Visible constructor.
     *
     * @param string[]|string|null $only
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    public function __construct(
        public array|string|null $only = null
    ) {}

}
