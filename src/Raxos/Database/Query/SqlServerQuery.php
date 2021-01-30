<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

/**
 * Class SqlServerQuery
 *
 * @package Raxos\Database\Query
 */
class SqlServerQuery extends Query
{

    /**
     * SQL Server has no 'optimize table' statement like MySQL. This method only works on tables with clustered indexes.
     * https://stackoverflow.com/questions/37570886/what-is-sql-servers-equivalent-to-mysqls-optimize-command
     * {@inheritDoc}
     */
    public function optimizeTable(string $table): static
    {
        $table = $this->dialect->escapeTable($table);

        return $this->addPiece("alter index all on {$table} rebuild");
    }

    /**
     * {@inheritdoc}
     */
    public function truncateTable(string $table): static
    {
        $table = $this->dialect->escapeTable($table);

        return $this->addPiece('truncate table', $table);
    }
}
