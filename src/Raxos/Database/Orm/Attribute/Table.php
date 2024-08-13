<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use JetBrains\PhpStorm\Pure;

/**
 * Class Table
 *
 * Defines the database table of the model.
 *
 * <code>
 *     #[Table('posts')]
 *     class Post extends Model {}
 * </code>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.0
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Table implements AttributeInterface
{

    /**
     * Table constructor.
     *
     * @param string|null $name
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    public function __construct(
        public ?string $name = null
    ) {}

}
