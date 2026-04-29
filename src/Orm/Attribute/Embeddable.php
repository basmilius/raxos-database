<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use Raxos\Contract\Database\Orm\AttributeInterface;

/**
 * Class Embeddable
 *
 * Marks a class as embeddable within a model. Embeddable classes
 * are value objects whose properties map to columns in the parent
 * model's database table.
 *
 * Raxos hydrates instances by calling `new $class()` and then
 * assigning each column directly to the matching property — the same
 * pattern used for ORM models. The class must therefore have a
 * no-argument constructor (implicit is fine), publicly writable
 * properties, and must not be marked `readonly`.
 *
 * <code>
 * #[Embeddable]
 * final class Address
 * {
 *     #[Column]
 *     public ?string $street = null;
 *
 *     #[Column]
 *     public ?string $city = null;
 * }
 * </code>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 2.2.0
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Embeddable implements AttributeInterface {}
