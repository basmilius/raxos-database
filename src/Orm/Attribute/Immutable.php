<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use Raxos\Contract\Database\Orm\AttributeInterface;

/**
 * Class Immutable
 *
 * Marks the column, marco or relation as immutable. No write actions
 * are allowed to the field.
 *
 * <code>
 * class User extends Model {
 *     #[Column]
 *     #[Immutable]
 *     public string $encryptionKey;
 * }
 * </code>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.17
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Immutable implements AttributeInterface {}
