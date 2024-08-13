<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;
use Raxos\Foundation\Collection\Arrayable;

/**
 * Class Alias
 *
 * Defines an alias for the column. Used within exports such as
 * {@see Arrayable::toArray()} and {@see JsonSerializable::jsonSerialize()}.
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
 * @since 1.0.0
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Alias implements AttributeInterface
{

    /**
     * Alias constructor.
     *
     * @param string $alias
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    public function __construct(
        public string $alias
    ) {}

}
