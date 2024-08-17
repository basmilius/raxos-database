<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;

/**
 * Class HasOne
 *
 * Defines a has one relation between two models. For example, a single
 * user can have a single address. The address belongs to the user.
 *
 * User 1...1 Address
 *
 * <code>
 *     class User extends Model {
 *         #[HasOne]
 *         public Address $address;
 *     }
 *
 *     class Address extends Model {
 *         #[BelongsTo]
 *         public User $user;
 *     }
 * </code>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.17
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class HasOne implements AttributeInterface, RelationAttributeInterface
{

    /**
     * HasOne constructor.
     *
     * @param string|null $referenceKey
     * @param string|null $referenceKeyTable
     * @param string|null $declaringKey
     * @param string|null $declaringKeyTable
     * @param bool $eagerLoad
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public ?string $referenceKey = null,
        public ?string $referenceKeyTable = null,
        public ?string $declaringKey = null,
        public ?string $declaringKeyTable = null,
        public bool $eagerLoad = false
    ) {}

}
