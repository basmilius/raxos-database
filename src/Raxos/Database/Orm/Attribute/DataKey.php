<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;

/**
 * Class DataKey
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.2
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class DataKey
{

    /**
     * DataKey constructor.
     *
     * @param string $key
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function __construct(public readonly string $key)
    {
    }

}
