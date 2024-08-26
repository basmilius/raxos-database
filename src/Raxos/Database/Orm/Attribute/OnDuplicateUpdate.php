<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;

/**
 * Class OnDuplicateUpdate
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.1.0
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class OnDuplicateUpdate implements AttributeInterface
{

    /**
     * OnDuplicateUpdate constructor.
     *
     * @param string[]|string $fields
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function __construct(
        public array|string $fields
    ) {}

}
