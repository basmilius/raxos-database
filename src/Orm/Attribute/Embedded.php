<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use Raxos\Contract\Database\Orm\AttributeInterface;

/**
 * Class Embedded
 *
 * Declares that a model property holds an embeddable value object.
 * The embeddable's column properties are stored in the parent model's
 * table, optionally prefixed with {@see Embedded::$prefix}.
 *
 * <code>
 * class User extends Model {
 *     #[Embedded(prefix: 'home_')]
 *     public Address $homeAddress;
 *
 *     #[Embedded(prefix: 'work_')]
 *     public ?Address $workAddress;
 * }
 * </code>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 2.2.0
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Embedded implements AttributeInterface
{

    /**
     * Embedded constructor.
     *
     * @param string $prefix
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.2.0
     */
    public function __construct(
        public string $prefix = ''
    ) {}

}
