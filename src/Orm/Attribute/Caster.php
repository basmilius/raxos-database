<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use Raxos\Database\Orm\Contract\{AttributeInterface, CasterInterface};

/**
 * Class Caster
 *
 * Defines a caster for the field. For example, a datetime within the
 * database can be 'cast' to a DateTime instance on php's side.
 *
 * <code>
 * class Post extends Model {
 *     #[Column]
 *     #[Caster(DateTimeCast::class)
 *     public DateTime $publishedOn;
 * }
 * </code>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.17
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Caster implements AttributeInterface
{

    /**
     * Caster constructor.
     *
     * @template TCaster of CasterInterface
     *
     * @param class-string<TCaster> $casterClass
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public string $casterClass
    ) {}

}
