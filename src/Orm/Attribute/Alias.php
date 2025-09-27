<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use Raxos\Contract\Collection\ArrayableInterface;
use Raxos\Contract\Database\Orm\AttributeInterface;

/**
 * Class Alias
 *
 * Defines an alias for the column. Used within exports such as
 * {@see ArrayableInterface::toArray()} and {@see JsonSerializable::jsonSerialize()}.
 * If `#[Alias]` is used without a value, the key of the property is used.
 *
 * <code>
 * class Post extends Model {
 *     #[ForeignKey]
 *     #[Alias('user_id')]
 *     public string $userId;
 * }
 * </code>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.17
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Alias implements AttributeInterface
{

    /**
     * Alias constructor.
     *
     * @param string|null $alias
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public ?string $alias = null
    ) {}

}
