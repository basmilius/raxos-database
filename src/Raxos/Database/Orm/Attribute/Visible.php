<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use Raxos\Database\Orm\Contract\AttributeInterface;

/**
 * Class Visible
 *
 * Defines that a field should be visible by default. If {@see Visible::$only}
 * is defined, only the fields specified of the value are visible.
 *
 * <code>
 * class Post extends Model {
 *     #[HasOne]
 *     #[Visible(['id', 'full_name'])]
 *     public User $creator;
 * }
 * </code>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.17
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
     * @since 1.0.17
     */
    public function __construct(
        public array|string|null $only = null
    ) {}

}
