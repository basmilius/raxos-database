<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use Generator;
use JetBrains\PhpStorm\ArrayShape;
use PDO;
use Raxos\Database\Connection\Connection;
use Raxos\Database\Dialect\Dialect;
use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Error\QueryException;
use Raxos\Database\Orm\Model;
use Raxos\Database\Orm\ModelArrayList;
use Raxos\Database\Query\Struct\AfterExpressionInterface;
use Raxos\Database\Query\Struct\BeforeExpressionInterface;
use Raxos\Database\Query\Struct\ComparatorAwareLiteral;
use Raxos\Database\Query\Struct\Value;
use Raxos\Foundation\Collection\ArrayList;
use Raxos\Foundation\Collection\CollectionException;
use Raxos\Foundation\PHP\MagicMethods\DebugInfoInterface;
use Raxos\Foundation\Util\ArrayUtil;
use stdClass;
use Stringable;
use function array_splice;
use function count;
use function implode;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function str_ends_with;
use function strlen;

/**
 * Class QueryBase
 *
 * @template TResult
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 1.0.0
 */
abstract class QueryBase implements DebugInfoInterface, Stringable
{

    private static int $index = 0;

    protected Dialect $dialect;

    protected string $currentClause = '';
    protected array $eagerLoad = [];
    protected ?string $modelClass = null;
    protected array $params = [];
    protected array $pieces = [];

    /**
     * QueryBase constructor.
     *
     * @param Connection $connection
     * @param bool $isPrepared
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(protected Connection $connection, protected bool $isPrepared = true)
    {
        ++self::$index;

        $this->dialect = $connection->getDialect();
    }

    /**
     * Gets the connection.
     *
     * @return Connection
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Adds an expression to the query.
     *
     * @param string $clause
     * @param Stringable|Value|string|int|float|bool|null $field
     * @param Stringable|Value|string|int|float|bool|null $comparator
     * @param Stringable|Value|string|int|float|bool|null $value
     *
     * @return static<TResult>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function addExpression(string $clause, Stringable|Value|string|int|float|bool|null $field = null, Stringable|Value|string|int|float|bool|null $comparator = null, Stringable|Value|string|int|float|bool|null $value = null): static
    {
        $afters = [];
        $befores = [];

        if ($field instanceof AfterExpressionInterface) {
            $afters[] = $field;
        }

        if ($comparator instanceof AfterExpressionInterface) {
            $afters[] = $comparator;
        }

        if ($value instanceof AfterExpressionInterface) {
            $afters[] = $value;
        }

        if ($field instanceof BeforeExpressionInterface) {
            $befores[] = $field;
        }

        if ($comparator instanceof BeforeExpressionInterface) {
            $befores[] = $comparator;
        }

        if ($value instanceof BeforeExpressionInterface) {
            $befores[] = $value;
        }

        if ($value === null && $comparator !== null) {
            $value = $comparator;
            $comparator = '=';
        }

        if ($value instanceof Value) {
            if ($value instanceof ComparatorAwareLiteral) {
                $comparator = null;
            }

            $value = $value->get($this);
        } else if ($comparator !== null) {
            $value = $this->addParam($value);
        }

        if ($field !== null) {
            if (is_string($field)) {
                $field = $this->dialect->escapeFields($field);
            } else if ($field instanceof Value) {
                $field = $field->get($this);
            }

            if ($comparator === null && $value !== null) {
                $expression = "{$field} {$value}";
            } else if ($comparator === null) {
                $expression = $field;
            } else {
                $expression = "{$field} {$comparator} {$value}";
            }
        } else {
            $expression = '';
        }

        foreach ($befores as $handler) {
            $handler->before($this);
        }

        $this->addPiece($clause, $expression);

        foreach ($afters as $handler) {
            $handler->after($this);
        }

        return $this;
    }

    /**
     * Adds a param and returns its name or when not in prepared mode, returns the
     * value as string or int.
     *
     * @param Stringable|Value|string|int|float|bool|null $value
     *
     * @return string|int
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function addParam(Stringable|Value|string|int|float|bool|null $value): string|int
    {
        if ($value instanceof Value) {
            $value = $value->get($this);
        }

        if (!$this->isPrepared) {
            if (is_int($value)) {
                return $value;
            }

            return $this->connection->quote((string)$value);
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if ($value instanceof Stringable) {
            $value = (string)$value;
        }

        $name = 'p' . static::$index . '_' . count($this->params);
        $this->params[] = [$name, $value];

        return ':' . $name;
    }

    /**
     * Adds a query piece.
     *
     * @param string $clause
     * @param array|string|int|null $data
     * @param string|null $separator
     *
     * @return static<TResult>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function addPiece(string $clause, array|string|int|null $data = null, ?string $separator = null): static
    {
        $this->pieces[] = [$clause, $data, $separator];

        if (strlen($clause) > 1) {
            $this->currentClause = $clause;
        }

        return $this;
    }

    /**
     * Executes the given function if the given bool is true.
     *
     * @param bool $is
     * @param callable $fn
     *
     * @return static<TResult>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function conditional(bool $is, callable $fn): static
    {
        if ($is) {
            $fn($this);
        }

        return $this;
    }

    /**
     * Wraps the given function with parenthesis or does nothing when the given bool is false.
     *
     * @param bool $is
     * @param callable $fn
     *
     * @return static<TResult>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function conditionalParenthesis(bool $is, callable $fn): static
    {
        if ($is) {
            return $this->parenthesis($fn);
        }

        return $this;
    }

    /**
     * Eager load the given relations when a Model is fetched from the database.
     *
     * @param string|string[] $relations
     *
     * @return static<TResult>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function eagerLoad(string|array $relations): static
    {
        if (is_string($relations)) {
            $this->eagerLoad[] = $relations;
        } else {
            foreach ($relations as $relation) {
                $this->eagerLoad[] = $relation;
            }
        }

        return $this;
    }

    /**
     * Merges the given query with the current one.
     *
     * @param QueryBase $query
     *
     * @return static<TResult>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function merge(self $query): static
    {
        foreach ($query->pieces as [$clause, $data, $separator]) {
            $this->pieces[] = [$clause, $data, $separator];
        }

        foreach ($query->params as $param) {
            $this->params[] = $param;
        }

        return $this;
    }

    /**
     * Wraps the given function in parenthesis.
     *
     * @param callable $fn
     * @param bool $patch
     *
     * @return static<TResult>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function parenthesis(callable $fn, bool $patch = true): static
    {
        $index = count($this->pieces);

        $this->parenthesisOpen();
        $fn($this);
        $this->parenthesisClose();

        if ($patch) {
            $clause = $this->pieces[$index + 1][0];
            $this->pieces[$index][0] = (!empty($clause) ? $clause . ' ' : '') . $this->pieces[$index][0];
            $this->pieces[$index + 1][0] = '';
        }

        return $this;
    }

    /**
     * Closes a parenthesis group.
     *
     * @return static<TResult>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function parenthesisClose(): static
    {
        return $this->addPiece(')');
    }

    /**
     * Opens a parenthesis group.
     *
     * @param string|null $field
     * @param string|null $comparator
     * @param Stringable|Value|string|int|float|bool|null $value
     *
     * @return static<TResult>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function parenthesisOpen(?string $field = null, ?string $comparator = null, Stringable|Value|string|int|float|bool|null $value = null): static
    {
        return $this->addExpression('(', $field, $comparator, $value);
    }

    /**
     * Adds the given raw expression to the query.
     *
     * @param string $expression
     *
     * @return static<TResult>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function raw(string $expression): static
    {
        return $this->addPiece($expression);
    }

    /**
     * Returns TRUE if the given clause is defined in the query.
     *
     * @param string $clause
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function isClauseDefined(string $clause): bool
    {
        foreach ($this->pieces as [$c]) {
            if ($c === $clause) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns TRUE when a model is associated to the query.
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function isModelQuery(): bool
    {
        return $this->modelClass !== null;
    }

    /**
     * Associates a model.
     *
     * @param string $class
     *
     * @return static<TResult>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function withModel(string $class): static
    {
        $this->modelClass = $class;

        return $this;
    }

    /**
     * Removes the associated model.
     *
     * @return static<TResult>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function withoutModel(): static
    {
        $this->modelClass = null;

        return $this;
    }

    /**
     * Puts the pieces together and builds the query.
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function toSql(): string
    {
        $query = '';

        foreach ($this->pieces as [$clause, $data, $separator]) {
            if (is_array($data)) {
                $data = implode($separator, $data);
            }

            $addSpace = !empty($query) && !str_ends_with($query, '(') && !str_ends_with($query, ' ');
            $pieces = [];

            if (!empty($clause)) {
                $pieces[] = $clause;

                if ($clause[0] === ',' || $clause[0] === ')') {
                    $addSpace = false;
                }
            }

            if (!empty($data)) {
                $pieces[] = $data;
            }

            $query .= ($addSpace ? ' ' : '') . implode(' ', $pieces);
        }

        return $query;
    }

    /**
     * Returns the result row count found based on the current query. The
     * select part of the query is removed.
     *
     * @return int
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function resultCount(): int
    {
        $result = $this->connection
            ->query()
            ->select('count(*)')
            ->from($this, 'n')
            ->single();

        return (int)$result['count(*)'];
    }

    /**
     * Returns the total rows found based on the current query. Any limit
     * clause is ignored and the select part is removed. This is useful for
     * queries used for pagination and such.
     *
     * @return int
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function totalCount(): int
    {
        $selectIndex = ArrayUtil::findIndex($this->pieces, fn(array $piece) => $piece[0] === 'select');
        $limitIndex = ArrayUtil::findIndex($this->pieces, fn(array $piece) => $piece[0] === 'limit');

        $query = new static($this->connection, $this->isPrepared);
        $query->params = $this->params;
        $query->pieces = $this->pieces;
        $query->pieces[$selectIndex][1] = 'count(*)';

        if ($limitIndex !== null) {
            array_splice($query->pieces, $limitIndex, 1);
        }

        $result = $query
            ->withoutModel()
            ->single();

        return (int)$result['count(*)'];
    }

    /**
     * Runs the query and returns an array containing all the results.
     *
     * @param int $fetchMode
     * @param array $options
     *
     * @return array<TResult>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Statement::array()
     */
    public function array(int $fetchMode = PDO::FETCH_ASSOC, array $options = []): array
    {
        return $this
            ->statement($options)
            ->array($fetchMode);
    }

    /**
     * Runs the query and returns an ArrayList containing all the results.
     *
     * @param int $fetchMode
     * @param array $options
     *
     * @return ArrayList<TResult>|ModelArrayList<TResult>
     * @throws CollectionException
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Statement::arrayList()
     */
    public function arrayList(int $fetchMode = PDO::FETCH_ASSOC, array $options = []): ArrayList|ModelArrayList
    {
        return $this
            ->statement($options)
            ->arrayList($fetchMode);
    }

    /**
     * Runs the query and returns a generator containing all results.
     *
     * @param int $fetchMode
     * @param array $options
     *
     * @return Generator<TResult>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Statement::cursor()
     */
    public function cursor(int $fetchMode = PDO::FETCH_ASSOC, array $options = []): Generator
    {
        return $this
            ->statement($options)
            ->cursor($fetchMode);
    }

    /**
     * Runs the query.
     *
     * @param array $options
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see Statement::run()
     */
    public function run(array $options = []): void
    {
        $this
            ->statement($options)
            ->run();
    }

    /**
     * Executes the query and returns the first row. When no result is found
     * null is returned.
     *
     * @param int $fetchMode
     * @param array $options
     *
     * @return Model|stdClass|array|null
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     *
     * @psalm-return TResult
     */
    public function single(int $fetchMode = PDO::FETCH_ASSOC, array $options = []): Model|stdClass|array|null
    {
        return $this
            ->statement($options)
            ->single($fetchMode);
    }

    /**
     * Executes the query and returns the first result. When no result is found
     * an query exception is thrown.
     *
     * @param int $fetchMode
     * @param array $options
     *
     * @return Model|stdClass|array
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     *
     * @psalm-return TResult
     */
    public function singleOrFail(int $fetchMode = PDO::FETCH_ASSOC, array $options = []): Model|stdClass|array
    {
        $result = $this->single($fetchMode, $options);

        if ($result === null) {
            throw new QueryException('No result was found.', QueryException::ERR_NO_RESULT);
        }

        return $result;
    }

    /**
     * Creates a statement with the current query.
     *
     * @param array $options
     *
     * @return Statement
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function statement(array $options = []): Statement
    {
        $statement = new Statement($this->connection, $this, $options);

        if ($this->modelClass !== null) {
            $statement->withModel($this->modelClass);
        }

        $statement->eagerLoad($this->eagerLoad);

        foreach ($this->params as [$name, $value]) {
            $statement->bind($name, $value);
        }

        return $statement;
    }

    /**
     * Resets the builder.
     *
     * @return static<TResult>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected final function reset(): static
    {
        $this->currentClause = '';
        $this->modelClass = null;
        $this->params = [];
        $this->pieces = [];

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[ArrayShape([
        'sql' => 'string',
        'type' => 'string',
        'params' => 'array'
    ])]
    public function __debugInfo(): ?array
    {
        return [
            'sql' => $this->toSql(),
            'type' => $this->isPrepared ? 'PREPARED' : 'RAW',
            'params' => $this->params
        ];
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __toString(): string
    {
        return $this->toSql();
    }

}
