<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use JetBrains\PhpStorm\Pure;

/**
 * Class Macro
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.0
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Macro implements AttributeInterface
{

    /**
     * Macro constructor.
     *
     * @param (callable&string)|(callable&array) $implementation
     * @param bool $cached
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    public function __construct(
        public string|array $implementation,
        public bool $cached = true
    ) {}

}
