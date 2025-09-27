<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use Raxos\Contract\Database\Orm\AttributeInterface;

/**
 * Class SoftDelete
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.6.1
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class SoftDelete implements AttributeInterface
{

    /**
     * SoftDelete constructor.
     *
     * @param string $column
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.6.1
     */
    public function __construct(
        public string $column = 'deleted_on'
    ) {}

}
