<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use JetBrains\PhpStorm\ArrayShape;
use Raxos\Database\Dialect\Dialect;
use Raxos\Database\Orm\Model;
use Raxos\Database\Orm\Structure\Structure;
use Raxos\Foundation\Contract\DebuggableInterface;
use function array_filter;
use function implode;

/**
 * Class ColumnLiteral
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 1.0.16
 */
final readonly class ColumnLiteral extends Literal implements DebuggableInterface
{

    /**
     * ColumnLiteral constructor.
     *
     * @param Dialect $dialect
     * @param string $column
     * @param string|null $table
     * @param string|null $database
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function __construct(
        private Dialect $dialect,
        public string $column,
        public ?string $table = null,
        public ?string $database = null
    )
    {
        $parts = array_filter([
            $database !== null ? $this->dialect->escapeField($this->database) : null,
            $table !== null ? $this->dialect->escapeField($this->table) : null,
            $this->dialect->escapeField($this->column)
        ]);

        parent::__construct(implode('.', $parts));
    }

    /**
     * Returns the foreign key form of the column.
     *
     * @param Structure<Model> $structure
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function asForeignKeyFor(Structure $structure): self
    {
        return new self(
            $this->dialect,
            "{$this->table}_{$this->column}",
            $structure->table,
            $this->database
        );
    }

    /**
     * Returns the foreign key form of the column.
     *
     * @param string $table
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function asForeignKeyForTable(string $table): self
    {
        return new self(
            $this->dialect,
            "{$this->table}_{$this->column}",
            $table,
            $this->database
        );
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    #[ArrayShape([
        'value' => 'string'
    ])]
    public function __debugInfo(): array
    {
        return [
            'value' => $this->value
        ];
    }

}
