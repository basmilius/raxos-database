<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use Raxos\Database\Orm\Contract\{AttributeInterface, RelationAttributeInterface};
use Raxos\Database\Orm\Model;

/**
 * Class BelongsToMany
 *
 * Defines a 'Belongs To Many' relations between models. For example, multiple users
 * can have multiple roles. This relation needs a linking table.
 *
 * [User] ∞...1 [RoleUser] 1...∞ [Role]
 *
 * <code>
 * class Role extends Model {
 *     #[BelongsToMany(User::class)]
 *     public ModelArrayList $users;
 * }
 * </code>
 *
 * <code>
 * class User extends Model {
 *     #[BelongsToMany(Role::class)]
 *     public ModelArrayList $roles;
 * }
 * </code>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.17
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class BelongsToMany implements AttributeInterface, RelationAttributeInterface
{

    /**
     * BelongsToMany constructor.
     *
     * @param class-string<Model> $referenceModel
     * @param string|null $linkingTable
     * @param string|null $referenceKey
     * @param string|null $referenceKeyTable
     * @param string|null $referenceLinkingKey
     * @param string|null $referenceLinkingKeyTable
     * @param string|null $declaringKey
     * @param string|null $declaringKeyTable
     * @param string|null $declaringLinkingKey
     * @param string|null $declaringLinkingKeyTable
     * @param bool $eagerLoad
     * @param string|null $orderBy
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public string $referenceModel,
        public ?string $linkingTable = null,
        public ?string $referenceKey = null,
        public ?string $referenceKeyTable = null,
        public ?string $referenceLinkingKey = null,
        public ?string $referenceLinkingKeyTable = null,
        public ?string $declaringKey = null,
        public ?string $declaringKeyTable = null,
        public ?string $declaringLinkingKey = null,
        public ?string $declaringLinkingKeyTable = null,
        public bool $eagerLoad = false,
        public ?string $orderBy = null
    ) {}

}
