<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use Raxos\Contract\Database\{ConnectionInterface, GrammarInterface};
use Raxos\Contract\Database\Orm\StructureInterface;
use Raxos\Contract\Database\Query\{QueryExpressionInterface, QueryInterface};
use function array_filter;
use function implode;

/**
 * Class ColumnRef
 *
 * A grammar-less column reference. The identifier is escaped lazily in
 * {@see self::compile()} using the grammar of the compiling query, so it can
 * be used to reference columns without a connection.
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Expression
 * @since 3.0.0
 */
final readonly class ColumnRef implements QueryExpressionInterface
{

    /**
     * ColumnRef constructor.
     *
     * @param string $column
     * @param string|null $table
     * @param string|null $schema
     *
     * @author Bas Milius <bas@mili.us>
     * @since 3.0.0
     */
    public function __construct(
        public string $column,
        public ?string $table = null,
        public ?string $schema = null
    ) {}

    /**
     * Returns the foreign key form of the column for a structure.
     *
     * @param StructureInterface $structure
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 3.0.0
     */
    public function asForeignKeyFor(StructureInterface $structure): self
    {
        return new self("{$this->table}_{$this->column}", $structure->table, $this->schema);
    }

    /**
     * Returns the foreign key form of the column for a table.
     *
     * @param string $table
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 3.0.0
     */
    public function asForeignKeyForTable(string $table): self
    {
        return new self("{$this->table}_{$this->column}", $table, $this->schema);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 3.0.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $parts = array_filter([
            $this->schema !== null ? $grammar->escape($this->schema) : null,
            $this->table !== null ? $grammar->escape($this->table) : null,
            $this->column !== '*' ? $grammar->escape($this->column) : $this->column,
        ]);

        $query->raw(implode('.', $parts));
    }

}
