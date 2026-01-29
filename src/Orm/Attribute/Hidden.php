<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use Raxos\Contract\Database\Orm\AttributeInterface;

/**
 * Class Hidden
 *
 * Marks the column, marco, or relation as hidden.
 *
 * <code>
 * class User extends Model {
 *     #[Column]
 *     #[Hidden]
 *     public string $password;
 * }
 * </code>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.17
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Hidden implements AttributeInterface {}
