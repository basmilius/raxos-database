<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Literal;

use Raxos\Database\Contract\{GrammarInterface, QueryLiteralInterface};
use Raxos\Database\Orm\Structure\Structure;
use function array_filter;

/**
 * Class ColumnLiteral
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Literal
 * @since 1.5.0
 */
final readonly class ColumnLiteral implements QueryLiteralInterface
{

    public string $literal;

    /**
     * ColumnLiteral constructor.
     *
     * @param GrammarInterface $grammar
     * @param string $column
     * @param string|null $table
     * @param string|null $schema
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public function __construct(
        private GrammarInterface $grammar,
        public string $column,
        public ?string $table = null,
        public ?string $schema = null
    )
    {
        $parts = array_filter([
            $this->schema !== null ? $this->grammar->escape($this->schema) : null,
            $this->table !== null ? $this->grammar->escape($this->table) : null,
            $this->column !== '*' ? $this->grammar->escape($this->column) : $this->column,
        ]);

        $this->literal = implode('.', $parts);
    }

    /**
     * Returns the foreign key form of the column for a structure.
     *
     * @param Structure $structure
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public function asForeignKeyFor(Structure $structure): self
    {
        return new self(
            $this->grammar,
            "{$this->table}_{$this->column}",
            $structure->table,
            $this->schema
        );
    }

    /**
     * Returns the foreign key form of the column for a table.
     *
     * @param string $table
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public function asForeignKeyForTable(string $table): self
    {
        return new self(
            $this->grammar,
            "{$this->table}_{$this->column}",
            $table,
            $this->schema
        );
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public function __toString(): string
    {
        return $this->literal;
    }

}
