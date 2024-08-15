<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;

/**
 * Class Alias
 *
 * Defines an alias for the column. Used within exports such as
 * {@see Arrayable::toArray()} and {@see JsonSerializable::jsonSerialize()}.
 * If alias is used without a value, the key of the property is used.
 *
 * <code>
 *     class Post extends Model {
 *         #[ForeignKey]
 *         #[Alias('user_id')]
 *         public string $userId;
 *     }
 * </code>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 13-08-2024
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
     * @since 13-08-2024
     */
    public function __construct(
        public ?string $alias = null
    ) {}

}
