<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;

/**
 * Class BelongsTo
 *
 * Defines a belongs to relation between two models. For example, multiple user
 * tokens belong to a single user. The user on the other hand can have multiple
 * user tokens, wich means a has many relation.
 *
 * UserToken ∞...1 User
 *
 * <code>
 *     class UserToken extends Model {
 *         #[BelongsTo]
 *         public User $user;
 *     }
 *
 *     class User extends Model {
 *         #[HasMany(UserToken::class)]
 *         public ModelArrayList $tokens;
 *     }
 * </code>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 15-08-2024
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class BelongsTo implements AttributeInterface, RelationAttributeInterface
{

    /**
     * BelongsTo constructor.
     *
     * @param string|null $referenceKey
     * @param string|null $referenceKeyTable
     * @param string|null $declaringKey
     * @param string|null $declaringKeyTable
     * @param bool $eagerLoad
     *
     * @author Bas Milius <bas@mili.us>
     * @since 15-08-2024
     */
    public function __construct(
        public ?string $referenceKey = null,
        public ?string $referenceKeyTable = null,
        public ?string $declaringKey = null,
        public ?string $declaringKeyTable = null,
        public bool $eagerLoad = false
    ) {}

}
