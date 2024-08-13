<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use JetBrains\PhpStorm\Pure;

/**
 * Class Hidden
 *
 * Marks the column, marco or relation as hidden.
 *
 * <code>
 *     class User extends Model {
 *         #[Column]
 *         #[Hidden]
 *         public string $password;
 *     }
 * </code>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.0
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Hidden implements AttributeInterface
{

    /**
     * Hidden constructor.
     *
     * @param string[]|string|null $only
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    #[Pure]
    public function __construct(
        public array|string|null $only = null
    ) {}

}
