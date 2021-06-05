<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use Generator;
use PDO;
use PDOStatement;
use Raxos\Database\Connection\Connection;
use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Error\QueryException;
use Raxos\Database\Orm\Model;
use Raxos\Database\Orm\ModelArrayList;
use Raxos\Foundation\Collection\ArrayList;
use Raxos\Foundation\Collection\CollectionException;
use Raxos\Foundation\Util\Stopwatch;
use stdClass;
use function array_filter;
use function array_map;
use function class_exists;
use function is_array;
use function is_int;

/**
 * Class Statement
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 1.0.0
 */
class Statement
{

    private array $eagerLoad = [];
    private PDOStatement $pdoStatement;
    private string $sql;

    /**
     * @template M of \Raxos\Database\Orm\Model
     * @var class-string<M>|null
     */
    private ?string $modelClass = null;

    /**
     * Statement constructor.
     *
     * @param Connection $connection
     * @param Query|string $query
     * @param array $options
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(private Connection $connection, private Query|string $query, private array $options = [])
    {
        $this->sql = $query instanceof Query ? $query->toSql() : $query;
        $this->pdoStatement = $connection->getPdo()->prepare($this->sql, $options);
    }

    /**
     * Statement destructor.
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __destruct()
    {
        $this->pdoStatement->closeCursor();
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
     * Gets the options for the query.
     *
     * @return array
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Gets the PDO statement.
     *
     * @return PDOStatement
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getPdoStatement(): PDOStatement
    {
        return $this->pdoStatement;
    }

    /**
     * Gets the query.
     *
     * @return Query|string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getQuery(): Query|string
    {
        return $this->query;
    }

    /**
     * Gets the sql query.
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Executes the statement and returns an array containing all results.
     *
     * @param int $fetchMode
     *
     * @return array
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function array(int $fetchMode = PDO::FETCH_ASSOC): array
    {
        $this->execute();

        return $this->fetchAll($fetchMode);
    }

    /**
     * Executes the statement and returns an ArrayList containing all results.
     *
     * @param int $fetchMode
     *
     * @return ArrayList|ModelArrayList
     * @throws CollectionException
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function arrayList(int $fetchMode = PDO::FETCH_ASSOC): ArrayList|ModelArrayList
    {
        $results = $this->array($fetchMode);
        $isAllModels = empty(array_filter($results, fn(mixed $result) => !($result instanceof Model)));

        if ($isAllModels) {
            return ModelArrayList::of($results);
        }

        return ArrayList::of($results);
    }

    /**
     * Executes the statement and returns a generator containing all results.
     *
     * @param int $fetchMode
     *
     * @return Generator
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function cursor(int $fetchMode = PDO::FETCH_ASSOC): Generator
    {
        $this->execute();

        while ($result = $this->fetch($fetchMode)) {
            yield $result;
        }
    }

    /**
     * Executes the statement.
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function run(): void
    {
        $this->execute();
    }

    /**
     * Executes the statement and returns the first result.
     *
     * @param int $fetchMode
     *
     * @return Model|stdClass|array|null
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function single(int $fetchMode = PDO::FETCH_ASSOC): Model|stdClass|array|null
    {
        $this->execute();

        return $this->fetch($fetchMode);
    }

    /**
     * Binds the given value.
     *
     * @param string $name
     * @param string|int|float|null $value
     * @param int|null $type
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function bind(string $name, string|int|float|null $value, ?int $type = null): static
    {
        $type ??= is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;

        $this->pdoStatement->bindValue($name, $value, $type);

        return $this;
    }

    /**
     * Creates a new model instance.
     *
     * @param mixed $result
     *
     * @return Model
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function createModel(mixed $result): Model
    {
        if ($this->modelClass === null) {
            throw new QueryException('Cannot create model instance, no model was assigned to the query.', QueryException::ERR_INVALID_MODEL);
        }

        /** @var Model&string $modelClass */
        $modelClass = $this->modelClass;

        if (!class_exists($modelClass)) {
            throw new QueryException('Cannot create model instance, the assigned model does not exist.', QueryException::ERR_INVALID_MODEL);
        }

        return $modelClass::createInstance($result);
    }

    /**
     * Enable eager loading for the given relationships.
     *
     * @param string[] $relationships
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function eagerLoad(array $relationships): void
    {
        $this->eagerLoad = $relationships;
    }

    /**
     * Fetches a single row.
     *
     * @param int $fetchMode
     *
     * @return Model|stdClass|array|null
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function fetch(int $fetchMode = PDO::FETCH_ASSOC): Model|stdClass|array|null
    {
        $result = $this->pdoStatement->fetch($fetchMode);

        if ($result === false) {
            return null;
        }

        if ($this->modelClass !== null) {
            $model = $this->createModel($result);

            $this->loadRelationships($model);

            return $model;
        }

        return $result;
    }

    /**
     * Fetches all rows.
     *
     * @param int $fetchMode
     *
     * @return array
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function fetchAll(int $fetchMode = PDO::FETCH_ASSOC): array
    {
        $results = $this->pdoStatement->fetchAll($fetchMode);

        if ($this->modelClass !== null) {
            $models = array_map(fn(mixed $result) => $this->createModel($result), $results);

            $this->loadRelationships($models);

            return $models;
        }

        return $results;
    }

    /**
     * Returns the amount of rows in the result.
     *
     * @return int
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function rowCount(): int
    {
        return $this->pdoStatement->rowCount();
    }

    /**
     * Associates a model.
     *
     * @param string $class
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function withModel(string $class): static
    {
        $this->modelClass = $class;

        return $this;
    }

    /**
     * Removes the associated model.
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function withoutModel(): static
    {
        $this->modelClass = null;

        return $this;
    }

    /**
     * Executes the pdo statement.
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private function execute(): void
    {
        if ($this->modelClass === null && !empty($this->eagerLoad)) {
            throw new QueryException('Eager loading is only available for models.', QueryException::ERR_EAGER_NOT_AVAILABLE);
        }

        if (($logger = $this->connection->getLogger()) !== null) {
            $result = Stopwatch::measure($time, fn() => $this->pdoStatement->execute(), Stopwatch::SECONDS);
            $logger->addQuery(new QueryLogEntry($this->pdoStatement->queryString, $time));
        } else {
            $result = $this->pdoStatement->execute();
        }

        if ($result === false) {
            throw $this->throwFromError();
        }
    }

    /**
     * Eager loads the relationships.
     *
     * @param Model|array $models
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private function loadRelationships(Model|array $models): void
    {
        if (!is_array($models)) {
            $models = [$models];
        }

        if (empty($models)) {
            return;
        }

        /** @var Model&string $modelClass */
        $modelClass = $this->modelClass;
        $modelClass::eagerLoadRelationships($models, $this->eagerLoad);
    }

    /**
     * Throws a database exception based on the last error.
     *
     * @return DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private function throwFromError(): DatabaseException
    {
        [, $code, $message] = $this->pdoStatement->errorInfo();

        return DatabaseException::throw($code, $message);
    }

}
