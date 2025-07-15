<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use Raxos\Database\Orm\Contract\{AttributeInterface, RelationAttributeInterface};
use Raxos\Database\Orm\Model;

/**
 * Class HasOneThrough
 *
 * Defines a 'Has One' relation between two models that goes through
 * another model. For example, an address belongs to an owner but goes
 * through a house.
 *
 * [Post] 1...1 [User] 1...1 [Country]
 *
 * <code>
 * class Post extends Model {
 *     #[BelongsTo]
 *     public User $user;
 *
 *     #[BelongsToThrough(User::class)]
 *     public Country $country;
 * }
 * </code>
 *
 * <code>
 * class User extends Model {
 *     #[BelongsTo]
 *     public Country $country;
 *
 *     #[HasMany(Post::class)]
 *     public ModelArrayList $posts;
 * }
 * </code>
 *
 * <code>
 * class Country extends Model {
 *     #[MasMany(User::class)]
 *     public ModelArrayList $users;
 *
 *     #[HasOneThrough(Post::class, User::class)]
 *     public Post $firstPost;
 * }
 * </code>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.1.0
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class HasOneThrough implements AttributeInterface, RelationAttributeInterface
{

    /**
     * HasOneThrough constructor.
     *
     * @param class-string<Model> $linkingModel
     * @param string|null $referenceKey
     * @param string|null $referenceKeyTable
     * @param string|null $referenceLinkingKey
     * @param string|null $referenceLinkingKeyTable
     * @param string|null $declaringLinkingKey
     * @param string|null $declaringLinkingKeyTable
     * @param string|null $declaringKey
     * @param string|null $declaringKeyTable
     * @param bool $eagerLoad
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function __construct(
        public string $linkingModel,
        public ?string $referenceKey = null,
        public ?string $referenceKeyTable = null,
        public ?string $referenceLinkingKey = null,
        public ?string $referenceLinkingKeyTable = null,
        public ?string $declaringLinkingKey = null,
        public ?string $declaringLinkingKeyTable = null,
        public ?string $declaringKey = null,
        public ?string $declaringKeyTable = null,
        public bool $eagerLoad = false
    ) {}

}
