<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;

/**
 * Class Visible
 *
 * Defines that a field should be visible by default. If {@see Visible::$only}
 * is defined, only those fields of the value of the column are visible.
 *
 * <code>
 *     class Post extends Model {
 *         #[HasOne]
 *         #[Visible(['id', 'full_name'])]
 *         public User $creator;
 *     }
 * </code>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 13-08-2024
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
     * @since 13-08-2024
     */
    public function __construct(
        public array|string|null $only = null
    ) {}

}
