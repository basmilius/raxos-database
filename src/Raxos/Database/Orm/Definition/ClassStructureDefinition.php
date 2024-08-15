<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Definition;

/**
 * Class ClassStructureDefinition
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Definition
 * @since 13-08-2024
 */
final readonly class ClassStructureDefinition
{

    /**
     * ClassStructureDefinition constructor.
     *
     * @param string $connectionId
     * @param PolymorphicDefinition|null $polymorphic
     * @param string $table
     *
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
     */
    public function __construct(
        public string $connectionId,
        public ?PolymorphicDefinition $polymorphic,
        public string $table
    ) {}

}
