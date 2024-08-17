<?php
declare(strict_types=1);

namespace Raxos\Database\Connection;

use Raxos\Database\Error\{ConnectionException, ExecutionException, QueryException, SchemaException};
use Raxos\Database\Orm\Error\{RelationException, StructureException};

/**
 * Class AbstractMySqlLikeConnection
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Connection
 * @since 1.0.17
 */
abstract class AbstractMySqlLikeConnection extends Connection
{

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function foundRows(): int
    {
        return $this->column(
            $this->query(prepared: false)
                ->select('found_rows()')
        );
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function loadDatabaseSchema(): array
    {
        try {
            $results = $this
                ->query(prepared: false)
                ->select(['TABLE_NAME', 'COLUMN_NAME'])
                ->from('information_schema.COLUMNS')
                ->where('TABLE_SCHEMA', $this->connector->database)
                ->array();

            $data = [];

            foreach ($results as ['TABLE_NAME' => $table, 'COLUMN_NAME' => $column]) {
                $data[$table] ??= [];
                $data[$table][] = $column;
            }

            return $data;
        } catch (ConnectionException|ExecutionException|QueryException|RelationException|StructureException $err) {
            throw SchemaException::failed($err);
        }
    }

}
