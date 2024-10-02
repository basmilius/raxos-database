<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use Raxos\Database\Contract\QueryInterface;
use Raxos\Database\Error\QueryException;
use Raxos\Database\Orm\Model;

/**
 * Class SqlServerQuery
 *
 * @template TModel of Model
 * @extends Query<TModel>
 * @implements QueryInterface<TModel>
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
            throw QueryException::invalid('A select clause is required to limit results.');
        }

        return $this->replaceClause('select', static function (array $piece) use ($limit): array {
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
            throw QueryException::invalid('A order by clause is required to offset results.');
        }

        return $this->addPiece('offset', "{$offset} rows");
    }

}
