<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use Raxos\Database\Orm\Contract\{AttributeInterface, RelationAttributeInterface};
use Raxos\Database\Orm\Model;

/**
 * Class BelongsToThrough
 *
 * Defines a BelongsTo relation between two models that goes through
 * another model. For example, an address belongs to an owner but goes
 * through a house.
 *
 * Address 1...1 House ∞...1 Owner
 *
 * ```
 * class Address extends Model {
 *     #[BelongsTo]
 *     public House $house;
 *
 *     #[BelongsToThrough(House::class)]
 *     public Owner $owner;
 * }
 * ```
 *
 * ```
 * class House extends Model {
 *     #[BelongsTo]
 *     public Owner $owner;
 *
 *     #[MasMany(Address::class)]
 *     public ModelArrayList $addresses;
 * }
 * ```
 *
 * ```
 * class Owner extends Model {
 *     #[MasMany(House::class)]
 *     public ModelArrayList $houses;
 *
 *     #[MasManyThrough(Address::class, House::class)]
 *     public ModelArrayList $addresses;
 * }
 * ```
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.1.0
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class BelongsToThrough implements AttributeInterface, RelationAttributeInterface
{

    /**
     * BelongsToThrough constructor.
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
