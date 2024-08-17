<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;

/**
 * Class Computed
 *
 * Marks a database column as computed, for example when a sub-query
 * result is used as a generated value. This makes sure that the column
 * isn't automatically added to insert and update queries.
 *
 * <code>
 *     class Post extends Model {
 *         #[Column]
 *         #[Computed]
 *         public string $claims;
 *
 *         public static function getDefaultFields(array $fields): array {
 *             return self::extendFields($fields, [
 *                 self::column('*') => true,
 *
 *                 'claims' => ...sub-query...
 *             ]);
 *         }
 *     }
 * </code>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.17
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Computed implements AttributeInterface {}
