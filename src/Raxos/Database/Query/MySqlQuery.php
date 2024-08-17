<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use Raxos\Database\Orm\Model;

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
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function optimizeTable(string $table): static
    {
        $table = $this->dialect->escapeTable($table);

        return $this->addPiece('optimize table', $table);
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
