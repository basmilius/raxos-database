<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use Raxos\Database\Error\QueryException;
use Raxos\Database\Orm\Model;

/**
 * Class SqlServerQuery
 *
 * @template TModel of Model
 * @template-extends Query<TModel>
 * @template-implements QueryInterface<TModel>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 1.0.0
 */
class SqlServerQuery extends Query
{

    /**
     * {@inheritdoc}
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function limit(int $limit, int $offset = 0): static
    {
        if ($offset > 0) {
            $this->offset($offset);
        }

        if ($this->isClauseDefined('offset')) {
            return $this->raw("fetch next {$limit} rows only");
        }

        if (!$this->isClauseDefined('select')) {
            throw new QueryException('A select clause is required to limit results.', QueryException::ERR_CLAUSE_NOT_DEFINED);
        }

        return $this->replaceClause('select', function (array $piece) use ($limit): array {
            $piece[0] = "select top {$limit}";

            return $piece;
        });
    }

    /**
     * {@inheritdoc}
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function offset(int $offset): static
    {
        if (!$this->isClauseDefined('order by')) {
            throw new QueryException('A order by clause is required to offset results.', QueryException::ERR_CLAUSE_NOT_DEFINED);
        }

        return $this->addPiece('offset', "{$offset} rows");
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function optimizeTable(string $table): static
    {
        $table = $this->dialect->escapeTable($table);

        return $this->addPiece("alter index all on {$table} rebuild");
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function truncateTable(string $table): static
    {
        $table = $this->dialect->escapeTable($table);

        return $this->addPiece('truncate table', $table);
    }

}
