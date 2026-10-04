<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use Override;
use Raxos\Contract\Database\Query\QueryInterface;
use Raxos\Database\Orm\Model;
use Raxos\Database\Query\Error\UnexpectedException;
use function implode;

/**
 * Class MySqlQuery
 *
 * @template TModel of Model
 * @extends Query<TModel>
 * @implements QueryInterface<TModel>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 1.0.0
 */
class MySqlQuery extends Query
{
    /**
     * Counts the result query while preserving grouping and distinct-result semantics.
     *
     * @param Query $query
     *
     * @return int
     * @author Bas Milius <bas@mili.us>
     * @since 3.2.0
     */
    #[Override]
    protected function countResults(Query $query): int
    {
        try {
            return parent::countResults($query);
        } catch (UnexpectedException $err) {
            if (($err->err->errorInfo[1] ?? null) !== 1060) {
                throw $err;
            }
        }

        // MySQL permits duplicate SELECT names but requires unique CTE columns.
        // LIMIT 0 discovers the projection width without fetching its rows.
        $probe = clone $query;
        $probe->removeClause('limit');
        $probe->removeClause('offset');
        $statement = $probe->limit(0)->statement();
        $statement->run();
        $columns = [];

        for ($index = 0; $index < $statement->pdoStatement->columnCount(); ++$index) {
            $columns[] = "__raxos_column_{$index}";
        }

        return (int)$this->connection->query()
            ->with('__raxos_count (' . implode(', ', $columns) . ')', $query)
            ->select(literal('count(*)'))
            ->from('__raxos_count')
            ->statement()
            ->fetchColumn();
    }
}
