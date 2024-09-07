<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use BackedEnum;
use Raxos\Database\Error\{ConnectionException, QueryException};
use Raxos\Database\Orm\Definition\{PropertyDefinition, RelationDefinition};
use Raxos\Database\Orm\Error\{RelationException, StructureException};
use Raxos\Database\Orm\Model;
use Raxos\Database\Orm\Structure\Structure;
use Raxos\Database\Query\Struct\{AfterExpressionInterface, ComparatorAwareLiteral, Literal, Select, SubQueryLiteral, ValueInterface};
use Stringable;
use function array_is_list;
use function array_keys;
use function array_map;
use function array_shift;
use function array_unique;
use function array_values;
use function assert;
use function count;
use function is_array;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function str_contains;
use function substr;
use function trim;

/**
 * Class Query
 *
 * @template TModel of Model
 * @extends QueryBase<TModel>
 * @implements QueryInterface<TModel>
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
        return $this->addPiece('delete', $this->grammar->escape($table));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function deleteFrom(string $table): static
    {
        return $this->addPiece('delete from', $this->grammar->escape($table));
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
            $tables = [$tables];
        }

        $tables = array_map($this->grammar->escape(...), $tables);

        if ($alias !== null && count($tables) === 1) {
            $tables = array_map(static fn(string $table): string => "{$table} as {$alias}", $tables);
        }

        return $this->addPiece('from', $tables, $this->grammar->tableSeparator);
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
        $fields = array_map($this->grammar->escape(...), $fields);

        return $this->addPiece('group by', $fields, $this->grammar->columnSeparator);
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

        $fields = array_map(fn(string $field): string => str_contains($field, '=') ? $field : $this->grammar->escape($field) . " = VALUES({$field})", $fields);

        return $this->addPiece('on duplicate key update', $fields, $this->grammar->columnSeparator);
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
                return $this->grammar->escape(substr($field, 0, -4)) . ' asc';
            }

            if (str_contains($field, ' desc') || str_contains($field, ' DESC')) {
                return $this->grammar->escape(substr($field, 0, -5)) . ' desc';
            }

            return $this->grammar->escape($field);
        }, $fields);

        return $this->addPiece('order by', $fields, $this->grammar->columnSeparator);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function orderByAsc(Literal|string $field): static
    {
        if (is_string($field)) {
            $field = $this->grammar->escape($field);
        }

        $clause = $this->currentClause === 'order by' ? trim($this->grammar->columnSeparator) : 'order by';

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
            $field = $this->grammar->escape($field);
        }

        $clause = $this->currentClause === 'order by' ? trim($this->grammar->columnSeparator) : 'order by';

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
        $expression = $this->grammar->escape((string)$field) . ' = ' . $value;

        if ($this->currentClause === 'set') {
            $index = count($this->pieces) - 1;
            $existing = $this->pieces[$index][1];

            if (!is_array($existing)) {
                $existing = [$existing];
            }

            $existing[] = $expression;

            $this->pieces[$index][1] = $existing;
        } else {
            $this->addPiece('set', $expression, $this->grammar->columnSeparator);
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
        $this->addPiece('update', $this->grammar->escape($table));

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
        $this->parenthesis(fn() => $this->addPiece('', $values, $this->grammar->columnSeparator));

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

        $structure = Structure::of($modelClass);

        foreach ($structure->primaryKey as $property) {
            if (empty($primaryKey)) {
                throw QueryException::primaryKeyMismatchTooFew($modelClass);
            }

            $value = array_shift($primaryKey);

            if (is_int($value) || is_float($value)) {
                $value = literal($value);
            }

            $this->where($structure->getColumn($property->name), $value);
        }

        if (!empty($primaryKey)) {
            throw QueryException::primaryKeyMismatchTooMany($modelClass);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function wherePrimaryKeyIn(string $modelClass, array $primaryKeys): static
    {
        $structure = Structure::of($modelClass);
        $properties = $structure->primaryKey;

        if (count($properties) === 1) {
            return $this->where($structure->getColumn($properties[0]->key), in($primaryKeys));
        }

        $columns = array_map(fn(PropertyDefinition $property) => $structure->getColumn($property->name), $properties);

        $this->where('1 = 1');

        foreach ($primaryKeys as $index => $primaryKey) {
            $this->parenthesis(function () use ($index, $primaryKey, $columns): void {
                foreach ($primaryKey as $columnIndex => $value) {
                    if ($columnIndex === 0 && $index > 0) {
                        $this->orWhere($columns[$columnIndex], $value);
                    } else {
                        $this->where($columns[$columnIndex], $value);
                    }
                }
            });
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
            throw QueryException::incomplete();
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
            throw QueryException::incomplete();
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
    public function select(Select|array|string|int $fields = []): static
    {
        return $this->baseSelect('select', $fields);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function selectDistinct(Select|array|string|int $fields = []): static
    {
        return $this->selectSuffix('distinct', $fields);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function selectFoundRows(Select|array|string|int $fields = []): static
    {
        return $this->selectSuffix('sql_calc_found_rows', $fields);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function selectSuffix(string $suffix, Select|array|string|int $fields = []): static
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
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function baseInsert(string $clause, string $table, array $fields): static
    {
        if (empty($fields)) {
            throw QueryException::incomplete();
        }

        $fields = array_map($this->grammar->escape(...), $fields);

        $this->addPiece($clause, $this->grammar->escape($table));
        $this->parenthesis(fn() => $this->addPiece('', $fields, $this->grammar->columnSeparator));

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
        $table = $this->grammar->escape($table);

        foreach ($this->pieces as $index => [$existingClause, $existingTable]) {
            if ($existingClause === 'where') {
                $this->position = $index;
            }

            // note(Bas): this filters out double joins, but we need to figure out
            //  if we still need to execute the given function.
            if (str_contains($existingClause, 'join') && $existingTable === $table) {
                return $this;
            }
        }

        $this->addPiece($clause, $table);

        $this->isOnDefined = false;

        if ($fn !== null) {
            $this->isDoingJoin = true;
            $fn($this);
            $this->isDoingJoin = false;
        }

        $this->isOnDefined = false;
        $this->position = null;

        return $this;
    }

    /**
     * Base function to create `select` expressions.
     *
     * @param string $clause
     * @param Select|array|string|int $fields
     *
     * @return static<TModel>
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function baseSelect(string $clause, Select|array|string|int $fields): static
    {
        if (empty($fields) || ($fields instanceof Select && $fields->isEmpty)) {
            if ($this->modelClass !== null) {
                return $this->addPiece($clause, $this->modelClass::col('*'));
            }

            return $this->addPiece($clause, '*');
        }

        if (is_int($fields)) {
            return $this->addPiece($clause, $fields);
        }

        if (is_string($fields)) {
            return $this->addPiece($clause, $this->grammar->escape($fields));
        }

        $result = [];

        if ($fields instanceof Select) {
            foreach ($fields->entries as $entry) {
                $alias = $entry->alias !== null ? $this->grammar->escape($entry->alias) : null;
                $value = $entry->value;

                if ($value instanceof QueryBaseInterface) {
                    assert($alias !== null);

                    $result[] = "({$value}) as {$alias}";
                } elseif ($value instanceof AfterExpressionInterface) {
                    assert($alias !== null);

                    $query = new static($this->connection);
                    $value->after($query);

                    $result[] = "({$query}) as {$alias}";
                } elseif ($value instanceof ValueInterface) {
                    $value = $value->get($this);

                    $result[] = $alias !== null ? "{$value} as {$alias}" : $value;
                } else {
                    $value = $this->grammar->escape($value);
                    $result[] = $alias !== null ? "{$value} as {$alias}" : $value;
                }
            }
        } elseif (!array_is_list($fields)) {
            foreach ($fields as $alias => $field) {
                $alias = $this->grammar->escape($alias);

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
                    $result[] = $this->grammar->escape($field) . ' as ' . $alias;
                }
            }
        } else {
            foreach ($fields as $field) {
                if (is_array($field) && count($field) === 2) {
                    $result[] = $this->grammar->escape($field[0]) . ' as ' . $this->grammar->escape($field[1]);
                } elseif (is_numeric($field)) {
                    $result[] = (string)$field;
                } elseif ($field instanceof ValueInterface) {
                    $result[] = $field->get($this);
                } else {
                    $result[] = $this->grammar->escape((string)$field);
                }
            }
        }

        return $this->addPiece($clause, array_unique($result), $this->grammar->columnSeparator);
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
     * @throws ConnectionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function baseWhereHas(string $relation, ?callable $fn, callable $clause, bool $negate = false): static
    {
        if ($this->modelClass === null) {
            throw QueryException::missingModel();
        }

        $structure = Structure::of($this->modelClass);
        $property = $structure->getProperty($relation);

        if (!($property instanceof RelationDefinition)) {
            throw StructureException::invalidRelation($structure->class, $property->name);
        }

        $relation = $structure->getRelation($property);
        $query = $relation->rawQuery();

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
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function baseWith(string $clause, string $name, QueryInterface $query): static
    {
        $this->addPiece($this->currentClause === $clause ? ',' : $clause, "{$name} as");
        $this->parenthesis(fn() => $this->merge($query), false);

        return $this;
    }

}
