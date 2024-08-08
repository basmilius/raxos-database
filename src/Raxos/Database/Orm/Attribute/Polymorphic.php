<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use JetBrains\PhpStorm\Pure;
use Raxos\Database\Orm\Model;

/**
 * Class Polymorphic
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.0
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Polymorphic implements AttributeInterface
{

    /**
     * Polymorphic constructor.
     *
     * @template TModel of Model
     *
     * @param string $column
     * @param array<string, class-string<TModel>> $map
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    public function __construct(
        public string $column = 'type',
        public array $map = []
    )
    {
    }

}
