<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;

/**
 * Class Column
 *
 * Defines a database column. When {@see Column::$key} is given,
 * that key will be used in communication with the database.
 *
 *  <code>
 *      class User extends Model {
 *          #[Column]
 *          public string $name;
 *      }
 *  </code>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.17
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Column implements AttributeInterface
{

    /**
     * Column constructor.
     *
     * @param string|null $key
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public ?string $key = null
    ) {}

}
