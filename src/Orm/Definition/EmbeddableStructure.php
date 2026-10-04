<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Definition;

use Raxos\Database\Orm\Attribute\Embeddable;

/**
 * Class EmbeddableStructure
 *
 * Holds the cached reflection data for an {@see Embeddable}
 * class. Column keys are stored without prefix; the prefix is applied when creating
 * the {@see EmbeddedDefinition} on the model.
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Definition
 * @since 2.2.0
 */
final readonly class EmbeddableStructure
{

    /**
     * EmbeddableStructure constructor.
     *
     * @param class-string $class
     * @param ColumnDefinition[] $columns
     * @param EmbeddedDefinition[] $embeddeds
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.2.0
     */
    public function __construct(
        public string $class,
        public array $columns,
        public array $embeddeds
    ) {}

}
