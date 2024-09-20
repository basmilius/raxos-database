<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;

/**
 * Class ForeignKey
 *
 * Defines a foreign key database column.
 *
 * ```
 * class Post extends Model {
 *     #[ForeignKey]
 *     public string $creatorId;
 * }
 * ```
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.17
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class ForeignKey extends Column {}
