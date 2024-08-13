<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use BackedEnum;
use Raxos\Database\Error\{DatabaseException, QueryException};
use Raxos\Database\Orm\{InternalStructure, Model};
use Raxos\Database\Query\Struct\{AfterExpressionInterface, ComparatorAwareLiteral, Literal, SubQueryLiteral, ValueInterface};
use Stringable;
use function array_is_list;
use function array_keys;
use function array_map;
use function array_shift;
use function array_values;
use function class_exists;
use function count;
use function is_array;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function is_subclass_of;
use function sprintf;
use function str_contains;
use function substr;
use function trim;

/**
 * Class Query
 *
 * @template TModel of Model
 * @template-extends QueryBase<TModel>
 * @template-implements QueryInterface<TModel>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 1.0.0
 */
abstract class Query extends QueryBase implements QueryInterface
{

    private bool $isDoingJoin = false;
    private bool $isOnDefined = false;

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function delete(string $table): static
    {
        return $this->addPiece('delete', $this->dialect->escapeTable($table));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function deleteFrom(string $table): static
    {
        return $this->addPiece('delete from', $this->dialect->escapeTable($table));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function from(QueryInterface|array|string $tables, ?string $alias = null): static
    {
        if ($tables instanceof self) {
            $this->addPiece('from');
            $this->parenthesisOpen();
            $this->raw($tables->toSql());
            $this->parenthesisClose();

            if ($alias !== null) {
                $this->addPiece('as', $alias);
            }

            return $this;
        }

        if (is_string($tables)) {
            if (class_exists($tables) && is_subclass_of($tables, Model::class)) {
                $tables = $tables::table();
            }

            $tables = [$tables];
        }

        $tables = array_map($this->dialect->escapeTable(...), $tables);

        if ($alias !== null && count($tables) === 1) {
            $tables = array_map(static fn(string $table): string => "{$table} as {$alias}", $tables);
        }

        return $this->addPiece('from', $tables, $this->dialect->tableSeparator);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function groupBy(Literal|array|string $fields): static
    {
        if (!is_array($fields)) {
            $fields = [$fields];
        }

        $fields = array_map(strval(...), $fields);
        $fields = array_map($this->dialect->escapeFields(...), $fields);

        return $this->addPiece('group by', $fields, $this->dialect->fieldSeparator);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function having(
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $lhs = null,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $rhs = null
    ): static
    {
        return $this->addExpression($this->isClauseDefined('having') ? 'and' : 'having', $lhs, $cmp, $rhs);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function havingExists(QueryInterface $query): static
    {
        return $this->having(SubQueryLiteral::exists($query));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function havingIn(Literal|string $field, array $options): static
    {
        return $this->having($field, ComparatorAwareLiteral::in($options));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.2
     */
    public function havingNotExists(QueryInterface $query): static
    {
        return $this->having(SubQueryLiteral::notExists($query));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function havingNotNull(Literal|string $field): static
    {
        return $this->having($field, ComparatorAwareLiteral::isNotNull());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function havingNotIn(Literal|string $field, array $options): static
    {
        return $this->having($field, ComparatorAwareLiteral::notIn($options));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function havingNull(Literal|string $field): static
    {
        return $this->having($field, ComparatorAwareLiteral::isNull());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function limit(int $limit, int $offset = 0): static
    {
        $this->addPiece('limit', $limit);

        if ($offset > 0) {
            $this->offset($offset);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function offset(int $offset): static
    {
        return $this->addPiece('offset', $offset);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function on(
        Stringable|ValueInterface|string|int|float|bool $lhs,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $rhs = null
    ): static
    {
        $didOn = $this->isOnDefined;
        $this->isOnDefined = true;

        return $this->addExpression($didOn ? 'and' : 'on', $lhs, $cmp, $rhs);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function onDuplicateKeyUpdate(array|string $fields): static
    {
        if (is_string($fields)) {
            $fields = [$fields];
        }

        $fields = array_map(fn(string $field): string => str_contains($field, '=') ? $field : $this->dialect->escapeFields($field) . " = VALUES({$field})", $fields);

        return $this->addPiece('on duplicate key update', $fields, $this->dialect->fieldSeparator);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function orWhere(
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $lhs = null,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $rhs = null
    ): static
    {
        return $this->addExpression('or', $lhs, $cmp, $rhs);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function orWhereExists(QueryInterface $query): static
    {
        return $this->orWhere(SubQueryLiteral::exists($query));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function orWhereHas(string $relation, ?callable $fn = null): static
    {
        return $this->baseWhereHas($relation, $fn, $this->orWhere(...));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function orWhereIn(Literal|string $field, array $options): static
    {
        return $this->orWhere($field, ComparatorAwareLiteral::in($options));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function orWhereNotExists(QueryInterface $query): static
    {
        return $this->orWhere(SubQueryLiteral::notExists($query));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function orWhereNotHas(string $relation, callable $fn): static
    {
        return $this->baseWhereHas($relation, $fn, $this->orWhere(...), true);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function orWhereNotIn(Literal|string $field, array $options): static
    {
        return $this->orWhere($field, ComparatorAwareLiteral::notIn($options));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function orWhereNotNull(Literal|string $field): static
    {
        return $this->orWhere($field, ComparatorAwareLiteral::isNotNull());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function orWhereNull(Literal|string $field): static
    {
        return $this->orWhere($field, ComparatorAwareLiteral::isNull());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function orWhereRelation(
        string $relation,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $lhs = null,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $rhs = null
    ): static
    {
        return $this->orWhereHas($relation, $lhs !== null ? static fn(self $query) => $query->where($lhs, $cmp, $rhs) : null);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function orderBy(Literal|array|string $fields): static
    {
        if ($fields instanceof Literal) {
            $fields = [$fields->get($this)];
        }

        if (is_string($fields)) {
            $fields = [$fields];
        }

        $fields = array_map(function (ValueInterface|string $field): string {
            if ($field instanceof ValueInterface) {
                $field = $field->get($this);
            }

            if (str_contains($field, ' asc') || str_contains($field, ' ASC')) {
                return $this->dialect->escapeFields(substr($field, 0, -4)) . ' asc';
            }

            if (str_contains($field, ' desc') || str_contains($field, ' DESC')) {
                return $this->dialect->escapeFields(substr($field, 0, -5)) . ' desc';
            }

            return $this->dialect->escapeFields($field);
        }, $fields);

        return $this->addPiece('order by', $fields, $this->dialect->fieldSeparator);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function orderByAsc(Literal|string $field): static
    {
        if (is_string($field)) {
            $field = $this->dialect->escapeFields($field);
        }

        $clause = $this->currentClause === 'order by' ? trim($this->dialect->fieldSeparator) : 'order by';

        return $this->addPiece($clause, $field . ' asc');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function orderByDesc(Literal|string $field): static
    {
        if (is_string($field)) {
            $field = $this->dialect->escapeFields($field);
        }

        $clause = $this->currentClause === 'order by' ? trim($this->dialect->fieldSeparator) : 'order by';

        return $this->addPiece($clause, $field . ' desc');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function set(
        Stringable|ValueInterface|string $field,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $value
    ): static
    {
        $value = $this->addParam($value);
        $expression = $this->dialect->escapeFields((string)$field) . ' = ' . $value;

        if ($this->currentClause === 'set') {
            $index = count($this->pieces) - 1;
            $existing = $this->pieces[$index][1];

            if (!is_array($existing)) {
                $existing = [$existing];
            }

            $existing[] = $expression;

            $this->pieces[$index][1] = $existing;
        } else {
            $this->addPiece('set', $expression, $this->dialect->fieldSeparator);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function union(QueryInterface $query): static
    {
        $this->addPiece('union');

        return $this->merge($query);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function unionAll(QueryInterface $query): static
    {
        $this->addPiece('union all');

        return $this->merge($query);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function update(string $table, ?array $pairs = null): static
    {
        $this->addPiece('update', $this->dialect->escapeTable($table));

        if ($pairs === null) {
            return $this;
        }

        foreach ($pairs as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function values(array $values): static
    {
        $values = array_map($this->addParam(...), $values);

        $this->addPiece($this->isClauseDefined('values') ? ', ' : 'values');
        $this->parenthesis(fn() => $this->addPiece('', $values, $this->dialect->fieldSeparator));

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function where(
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $lhs = null,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $rhs = null
    ): static
    {
        return $this->addExpression($this->isClauseDefined('where') || $this->isDoingJoin ? 'and' : 'where', $lhs, $cmp, $rhs);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function whereExists(QueryInterface $query): static
    {
        return $this->where(SubQueryLiteral::exists($query));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function whereHas(string $relation, ?callable $fn = null): static
    {
        return $this->baseWhereHas($relation, $fn, $this->where(...));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function whereIn(Literal|string $field, array $options): static
    {
        return $this->where($field, ComparatorAwareLiteral::in($options));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function whereNotExists(QueryInterface $query): static
    {
        return $this->where(SubQueryLiteral::notExists($query));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function whereNotHas(string $relation, callable $fn): static
    {
        return $this->baseWhereHas($relation, $fn, $this->where(...), true);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function whereNotIn(Literal|string $field, array $options): static
    {
        return $this->where($field, ComparatorAwareLiteral::notIn($options));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function whereNotNull(Literal|string $field): static
    {
        return $this->where($field, ComparatorAwareLiteral::isNotNull());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function whereNull(Literal|string $field): static
    {
        return $this->where($field, ComparatorAwareLiteral::isNull());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function wherePrimaryKey(string $modelClass, array|int|string $primaryKey): static
    {
        if (!is_array($primaryKey)) {
            $primaryKey = [$primaryKey];
        }

        foreach (InternalStructure::getColumns($modelClass) as $definition) {
            if (!$definition->isPrimary) {
                continue;
            }

            if (empty($primaryKey)) {
                throw new QueryException(sprintf('Too few primary key values for model "%s".', $modelClass), QueryException::ERR_PRIMARY_KEY_MISMATCH);
            }

            $value = array_shift($primaryKey);
            $columnName = $definition->key;

            if (is_int($value) || is_float($value)) {
                $value = literal($value);
            }

            $this->where($modelClass::col($columnName), $value);
        }

        if (!empty($primaryKey)) {
            throw new QueryException(sprintf('Too many primary key values for model "%s".', $modelClass), QueryException::ERR_PRIMARY_KEY_MISMATCH);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function whereRelation(
        string $relation,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $lhs = null,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $rhs = null
    ): static
    {
        return $this->whereHas($relation, $lhs !== null ? static fn(self $query) => $query->where($lhs, $cmp, $rhs) : null);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function insertInto(string $table, array $fields): static
    {
        return $this->baseInsert('insert into', $table, $fields);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function insertIgnoreInto(string $table, array $fields): static
    {
        return $this->baseInsert('insert ignore into', $table, $fields);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function insertIntoValues(string $table, array $pairs): static
    {
        if (empty($pairs)) {
            throw new QueryException('There must be at least one item.', QueryException::ERR_MISSING_FIELDS);
        }

        if (array_is_list($pairs)) {
            $this->insertInto($table, array_keys($pairs[0]));

            foreach ($pairs as $pair) {
                $this->values(array_values($pair));
            }
        } else {
            $this->insertInto($table, array_keys($pairs));
            $this->values(array_values($pairs));
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function insertIgnoreIntoValues(string $table, array $pairs): static
    {
        if (empty($pairs)) {
            throw new QueryException('There must be at least one item.', QueryException::ERR_MISSING_FIELDS);
        }

        if (array_is_list($pairs)) {
            $this->insertIgnoreInto($table, array_keys($pairs[0]));

            foreach ($pairs as $pair) {
                $this->values(array_values($pair));
            }
        } else {
            $this->insertIgnoreInto($table, array_keys($pairs));
            $this->values(array_values($pairs));
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function replaceInto(string $table, array $fields): static
    {
        return $this->baseInsert('replace into', $table, $fields);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function replaceIntoValues(string $table, array $pairs): static
    {
        $fields = array_keys($pairs);
        $values = array_values($pairs);

        $this->replaceInto($table, $fields);
        $this->values($values);

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function select(array|string|int $fields = []): static
    {
        return $this->baseSelect('select', $fields);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function selectDistinct(array|string|int $fields = []): static
    {
        return $this->selectSuffix('distinct', $fields);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function selectFoundRows(array|string|int $fields = []): static
    {
        return $this->selectSuffix('sql_calc_found_rows', $fields);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function selectSuffix(string $suffix, array|string|int $fields = []): static
    {
        return $this->baseSelect("select {$suffix}", $fields);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function fullJoin(string $table, ?callable $fn = null): static
    {
        return $this->baseJoin('full join', $table, $fn);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function innerJoin(string $table, ?callable $fn = null): static
    {
        return $this->baseJoin('inner join', $table, $fn);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function join(string $table, ?callable $fn = null): static
    {
        return $this->baseJoin('join', $table, $fn);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function leftJoin(string $table, ?callable $fn = null): static
    {
        return $this->baseJoin('left join', $table, $fn);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function leftOuterJoin(string $table, ?callable $fn = null): static
    {
        return $this->baseJoin('left outer join', $table, $fn);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function rightJoin(string $table, ?callable $fn = null): static
    {
        return $this->baseJoin('right join', $table, $fn);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function with(string $name, QueryInterface $query): static
    {
        return $this->baseWith('with', $name, $query);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function withRecursive(string $name, QueryInterface $query): static
    {
        return $this->baseWith('with recursive', $name, $query);
    }

    /**
     * Base function to create `insert` expressions.
     *
     * @param string $clause
     * @param string $table
     * @param array $fields
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function baseInsert(string $clause, string $table, array $fields): static
    {
        if (empty($fields)) {
            throw new QueryException('Insert queries require fields.', QueryException::ERR_MISSING_FIELDS);
        }

        $fields = array_map($this->dialect->escapeFields(...), $fields);

        $this->addPiece($clause, $this->dialect->escapeTable($table));
        $this->parenthesis(fn() => $this->addPiece('', $fields, $this->dialect->fieldSeparator));

        return $this;
    }

    /**
     * Base function to create `join` expressions.
     *
     * @param string $clause
     * @param string $table
     * @param callable|null $fn
     *
     * @return static<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function baseJoin(string $clause, string $table, ?callable $fn): static
    {
        $this->addPiece($clause, $this->dialect->escapeTable($table));

        $this->isOnDefined = false;

        if ($fn !== null) {
            $this->isDoingJoin = true;
            $fn($this);
            $this->isDoingJoin = false;
        }

        $this->isOnDefined = false;

        return $this;
    }

    /**
     * Base function to create `select` expressions.
     *
     * @param string $clause
     * @param array|string|int $fields
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function baseSelect(string $clause, array|string|int $fields): static
    {
        if (empty($fields)) {
            if ($this->modelClass !== null) {
                return $this->addPiece($clause, $this->modelClass::column('*'));
            }

            return $this->addPiece($clause, '*');
        }

        if (is_int($fields)) {
            return $this->addPiece($clause, $fields);
        }

        if (is_string($fields)) {
            return $this->addPiece($clause, $this->dialect->escapeFields($fields));
        }

        $result = [];

        if (!array_is_list($fields)) {
            foreach ($fields as $alias => $field) {
                $alias = $this->dialect->escapeFields($alias);

                if ($field === null || $field === true) {
                    $result[] = $alias;
                } elseif ($field instanceof QueryBaseInterface) {
                    $sql = $field->toSql();

                    $result[] = "({$sql}) as {$alias}";
                } elseif ($field instanceof AfterExpressionInterface) {
                    $query = new static($this->connection);
                    $field->after($query);
                    $sql = $query->toSql();

                    $result[] = "({$sql}) as {$alias}";
                } elseif ($field instanceof ValueInterface) {
                    $field = $field->get($this);

                    $result[] = "{$field} as {$alias}";
                } else {
                    $result[] = $this->dialect->escapeFields($field) . ' as ' . $alias;
                }
            }
        } else {
            foreach ($fields as $field) {
                if (is_array($field) && count($field) === 2) {
                    $result[] = $this->dialect->escapeFields($field[0]) . ' as ' . $this->dialect->escapeFields($field[1]);
                } elseif (is_numeric($field)) {
                    $result[] = (string)$field;
                } elseif ($field instanceof ValueInterface) {
                    $result[] = $field->get($this);
                } else {
                    $result[] = $this->dialect->escapeFields((string)$field);
                }
            }
        }

        return $this->addPiece($clause, $result, $this->dialect->fieldSeparator);
    }

    /**
     * Base function to create `[where|and|or] (not) exists (query)` expressions.
     *
     * @param string $relation
     * @param callable|null $fn
     * @param callable $clause
     * @param bool $negate
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function baseWhereHas(string $relation, ?callable $fn, callable $clause, bool $negate = false): static
    {
        if ($this->modelClass === null) {
            throw new QueryException('The query needs a model to use the whereHas function.', QueryException::ERR_INVALID_MODEL);
        }

        $field = InternalStructure::getField($this->modelClass, $relation) ?? throw new QueryException(sprintf('Could not find relationship %s in model %s.', $relation, $this->modelClass), QueryException::ERR_FIELD_NOT_FOUND);
        $query = InternalStructure::getRelation($this->modelClass, $field)
            ->rawQuery();

        if ($fn !== null) {
            $fn($query);
        }

        $clause();
        $this->raw($negate ? 'not exists (' : 'exists (');
        $this->merge($query);
        $this->raw(')');

        return $this;
    }

    /**
     * Base function to create `with` expressions.
     *
     * @param string $clause
     * @param string $name
     * @param QueryInterface $query
     *
     * @return static<TModel>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function baseWith(string $clause, string $name, QueryInterface $query): static
    {
        $this->addPiece($this->currentClause === $clause ? ',' : $clause, "{$name} as");
        $this->parenthesis(fn() => $this->merge($query), false);

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public abstract function optimizeTable(string $table): static;

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public abstract function truncateTable(string $table): static;

}
