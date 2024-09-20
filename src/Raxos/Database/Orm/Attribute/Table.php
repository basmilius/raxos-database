<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use Raxos\Database\Orm\Contract\AttributeInterface;

/**
 * Class Table
 *
 * Defines the database table of the model.
 *
 * ```
 * #[Table('posts')]
 * class Post extends Model {}
 * ```
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.17
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Table implements AttributeInterface
{

    /**
     * Table constructor.
     *
     * @param string $name
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public string $name
    ) {}

}
