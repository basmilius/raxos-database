<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use JetBrains\PhpStorm\Pure;

/**
 * Class BelongsTo
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.16
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
     * @since 1.0.16
     */
    #[Pure]
    public function __construct(
        public ?string $referenceKey = null,
        public ?string $referenceKeyTable = null,
        public ?string $declaringKey = null,
        public ?string $declaringKeyTable = null,
        public bool $eagerLoad = false
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public static function __set_state(array $state): self
    {
        return new self(
            $state['referenceKey'],
            $state['referenceKeyTable'],
            $state['declaringKey'],
            $state['declaringKeyTable'],
            $state['eagerLoad']
        );
    }

}
