<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use JetBrains\PhpStorm\Pure;
use Raxos\Database\Orm\Cast\CastInterface;

/**
 * Class Caster
 *
 * Defines a caster for the field. For example, a datetime within the
 * database can be 'cast' to a DateTime instance on php's side.
 *
 * <code>
 *     class Post extends Model {
 *         #[Column]
 *         #[Caster(DateTimeCast::class)
 *         public DateTime $publishedOn;
 *     }
 * </code>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.0
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Caster implements AttributeInterface
{

    /**
     * Caster constructor.
     *
     * @template C of CastInterface
     *
     * @param class-string<C> $caster
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    public function __construct(
        public string $caster
    ) {}

}
