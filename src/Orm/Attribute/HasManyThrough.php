<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use Raxos\Contract\Database\Orm\{AttributeInterface, RelationAttributeInterface};
use Raxos\Database\Orm\Model;

/**
 * Class HasManyThrough
 *
 * Defines a 'Has Many' relation between two models that goes through another
 * model. For example, a user can have multiple garages which contain multiple
 * cars. If we want a relation between user and car, we can use this relation.
 *
 * [User] 1...∞ [Garage] 1...∞ [Car]
 *
 * <code>
 * class User extends Model {
 *     #[HasManyThrough(Car::class, Garage::class)]
 *     public ModelArrayList $cars;
 *
 *     #[HasMany(Garage::class)]
 *     public ModelArrayList $garages;
 * }
 * </code>
 *
 * <code>
 * class Garage extends Model {
 *     #[HasMany(Car::class)]
 *     public ModelArrayList $cars;
 *
 *     #[BelongsTo]
 *     public User $user;
 * }
 * </code>
 *
 * <code>
 * class Car extends Model {
 *     #[BelongsTo]
 *     public Garage $garage;
 * }
 * </code>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.17
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class HasManyThrough implements AttributeInterface, RelationAttributeInterface
{

    /**
     * HasManyThrough constructor.
     *
     * @param class-string<Model> $referenceModel
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
     * @param string|null $orderBy
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public string $referenceModel,
        public string $linkingModel,
        public ?string $referenceKey = null,
        public ?string $referenceKeyTable = null,
        public ?string $referenceLinkingKey = null,
        public ?string $referenceLinkingKeyTable = null,
        public ?string $declaringLinkingKey = null,
        public ?string $declaringLinkingKeyTable = null,
        public ?string $declaringKey = null,
        public ?string $declaringKeyTable = null,
        public bool $eagerLoad = false,
        public ?string $orderBy = null
    ) {}

}
