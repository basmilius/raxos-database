<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use Raxos\Database\Contract\QueryInterface;
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
class MySqlQuery extends Query {}
