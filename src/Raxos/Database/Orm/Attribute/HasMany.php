<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use Raxos\Database\Orm\Contract\{AttributeInterface, RelationAttributeInterface};
use Raxos\Database\Orm\Model;

/**
 * Class HasMany
 *
 * Defines a 'Has Many' relation between two models. For example, a single
 * user can have multiple addresses. The address, on the other hand, belongs
 * to a single user.
 *
 * [User] 1...∞ [Address]
 *
 * <code>
 * class User extends Model {
 *     #[HasMany(Address:class)]
 *     public ModelArrayList $addresses;
 * }
 * </code>
 *
 * <code>
 * class Address extends Model {
 *     #[BelongsTo]
 *     public User $user;
 * }
 * </code>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.17
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class HasMany implements AttributeInterface, RelationAttributeInterface
{

    /**
     * HasMany constructor.
     *
     * @param class-string<Model> $referenceModel
     * @param string|null $referenceKey
     * @param string|null $referenceKeyTable
     * @param string|null $declaringKey
     * @param string|null $declaringKeyTable
     * @param bool $eagerLoad
     * @param string|null $orderBy
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public string $referenceModel,
        public ?string $referenceKey = null,
        public ?string $referenceKeyTable = null,
        public ?string $declaringKey = null,
        public ?string $declaringKeyTable = null,
        public bool $eagerLoad = false,
        public ?string $orderBy = null
    ) {}

}
