<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Definition;

use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;
use Raxos\Database\Orm\Model;

/**
 * Class PolymorphicDefinition
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Definition
 * @since 1.0.17
 */
final readonly class PolymorphicDefinition implements JsonSerializable
{

    /**
     * PolymorphicDefinition constructor.
     *
     * @param string $column
     * @param array<string, class-string<Model>> $map
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public string $column,
        public array $map
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    #[ArrayShape([
        'column' => 'string',
        'map' => 'array<string, class-string<Model>>'
    ])]
    public function jsonSerialize(): array
    {
        return [
            'column' => $this->column,
            'map' => $this->map
        ];
    }

}
