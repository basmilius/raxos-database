<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Definition;

use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;

/**
 * Class ClassStructureDefinition
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Definition
 * @since 1.0.17
 */
final readonly class ClassStructureDefinition implements JsonSerializable
{

    /**
     * ClassStructureDefinition constructor.
     *
     * @param string $connectionId
     * @param PolymorphicDefinition|null $polymorphic
     * @param string $table
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public string $connectionId,
        public ?PolymorphicDefinition $polymorphic,
        public string $table
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    #[ArrayShape([
        'connection_id' => 'string',
        'polymorphic' => 'Raxos\Database\Orm\Definition\PolymorphicDefinition|null',
        'table' => 'string'
    ])]
    public function jsonSerialize(): array
    {
        return [
            'connection_id' => $this->connectionId,
            'polymorphic' => $this->polymorphic,
            'table' => $this->table
        ];
    }

}
