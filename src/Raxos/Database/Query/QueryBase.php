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
use function array_column;
use function array_splice;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function sprintf;
use function str_ends_with;

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
    protected array $eagerLoadDisable = [];
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
                $field = $value->get($this);
            }

            if ($comparator === null && $value !== null) {
                $expression = "{$field} {$value}";
            } else if ($comparator === null) {
                $expression = $field;
            } else {
                $expression = "{$field} {$comparator} {$value}";
            }
        } else {
            $expression = null;
        }

        $args = [$field, $comparator, $value];

        foreach ($args as $arg) {
            if ($arg instanceof BeforeExpressionInterface) {
                $arg->before($this);
            }
        }

        $this->addPiece($clause, $expression);

        foreach ($args as $arg) {
            if ($arg instanceof AfterExpressionInterface) {
                $arg->after($this);
            }
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

        if (isset($clause[2])) {
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
     * Disables eager loading for the given relation(s).
     *
     * @param string|string[] $relations
     *
     * @return static<TResult>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function eagerLoadDisable(string|array $relations): static
    {
        if (is_string($relations)) {
            $this->eagerLoadDisable[] = $relations;
        } else {
            foreach ($relations as $relation) {
                $this->eagerLoadDisable[] = $relation;
            }
        }

        return $this;
    }

    /**
     * Removes eager loading from the query.
     *
     * @return static<TResult>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function eagerLoadReset(): static
    {
        $this->eagerLoad = [];
        $this->eagerLoadDisable = [];

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
        $clauses = array_column($this->pieces, 0);

        return in_array($clause, $clauses);
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
     * Removes the given clause from the query.
     *
     * @param string $clause
     *
     * @return static<TResult>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function removeClause(string $clause): static
    {
        $index = ArrayUtil::findIndex($this->pieces, fn(array $piece) => $piece[0] === $clause);

        if ($index !== null) {
            array_splice($this->pieces, $index, 1);

            while (isset($this->pieces[$index]) && $this->pieces[$index][0] === ',') {
                array_splice($this->pieces, $index, 1);
            }
        }

        return $this;
    }

    /**
     * Replaces the given clause using the given function. The function
     * receives the array piece.
     *
     * @param string $clause
     * @param callable $fn
     *
     * @return static<TResult>
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function replaceClause(string $clause, callable $fn): static
    {
        $index = ArrayUtil::findIndex($this->pieces, fn(array $piece) => $piece[0] === $clause);

        if ($index === null) {
            throw new QueryException(sprintf('Clause "%s" is not defined in the query.', $clause), QueryException::ERR_CLAUSE_NOT_DEFINED);
        }

        $this->pieces[$index] = $fn($this->pieces[$index]);

        return $this;
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
        $pieces = [];

        foreach ($this->pieces as [$clause, $data, $separator]) {
            if (is_array($data)) {
                $data = implode($separator ?? ',', $data);
            }

            $addSpace = !empty($query) && !str_ends_with($query, '(') && !str_ends_with($query, ' ');

            if (!empty($clause)) {
                if ($clause[0] === ',' || $clause[0] === ')') {
                    $addSpace = false;
                }

                if ($addSpace) {
                    $pieces[] = ' ';
                }

                $pieces[] = $clause;
            } else if ($addSpace) {
                $pieces[] = ' ';
            }

            if (!empty($data)) {
                $pieces[] = $data;
            }
        }

        return implode(' ', $pieces);
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
        /** @var self $query */
        $query = $this->connection
            ->query()
            ->select('count(*)')
            ->from($this, '__n__');

        $query->params = $this->params;

        $result = $query->single();

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
        $original = clone $this;

        if (!$original->isClauseDefined('having')) {
            $original->replaceClause('select', function (array $piece): array {
                $piece[1] = 1;

                return $piece;
            });
        }

        $original->removeClause('limit');
        $original->removeClause('offset');
        $original->removeClause('order by');

        /** @var self $query */
        $query = $original->connection
            ->query()
            ->select('count(*)')
            ->from($original, '__n__');

        $query->params = $original->params;

        $result = $query->single();

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
     * @return Model|stdClass|array|null|mixed
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     *
     * @noinspection PhpDocSignatureInspection
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
     * @return Model|stdClass|array|mixed
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     *
     * @noinspection PhpDocSignatureInspection
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
        $statement->eagerLoadDisable($this->eagerLoadDisable);

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
