<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use Raxos\Database\Orm\Model;

/**
 * Class MariaDbQuery
 *
 * @template TModel of Model
 * @template-extends Query<TModel>
 * @template-implements QueryInterface<TModel>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 1.0.0
 */
class MariaDbQuery extends MySqlQuery
{
}
