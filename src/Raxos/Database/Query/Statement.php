<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use Generator;
use PDO;
use PDOStatement;
use Raxos\Database\Connection\ConnectionInterface;
use Raxos\Database\Error\{ConnectionException, ExecutionException, QueryException};
use Raxos\Database\Logger\QueryEvent;
use Raxos\Database\Orm\{Error\RelationException, Model, ModelArrayList};
use Raxos\Database\Orm\Error\StructureException;
use Raxos\Database\Orm\Structure\Structure;
use Raxos\Foundation\Collection\ArrayList;
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

    public readonly PDOStatement $pdoStatement;
    public readonly string $sql;

    private array $eagerLoad = [];
    private array $eagerLoadDisable = [];

    /**
     * @var class-string<Model>|null
     */
    private ?string $modelClass = null;

    /**
     * Statement constructor.
     *
     * @param ConnectionInterface $connection
     * @param QueryInterface|string $query
     * @param array $options
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        public readonly ConnectionInterface $connection,
        public readonly QueryInterface|string $query,
        public readonly array $options = []
    )
    {
        $this->sql = $query instanceof QueryInterface ? $query->toSql() : $query;
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
     * Executes the statement and returns an array containing all results.
     *
     * @param int $fetchMode
     *
     * @return array
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
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
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function arrayList(int $fetchMode = PDO::FETCH_ASSOC): ArrayList|ModelArrayList
    {
        $results = $this->array($fetchMode);
        $isAllModels = empty(array_filter($results, static fn(mixed $result) => !($result instanceof Model)));

        if ($isAllModels) {
            return new ModelArrayList($results);
        }

        return new ArrayList($results);
    }

    /**
     * Executes the statement and returns a generator containing all results.
     *
     * @param int $fetchMode
     *
     * @return Generator
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
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
     * @throws ExecutionException
     * @throws QueryException
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
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
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
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function createModel(mixed $result): Model
    {
        if ($this->modelClass === null) {
            throw new QueryException('Cannot create model instance, no model was assigned to the query.', QueryException::ERR_INVALID_MODEL);
        }

        if (!is_array($result)) {
            throw new QueryException('Cannot create model instance, the record set must be an array.', QueryException::ERR_INVALID_MODEL);
        }

        if (!class_exists($this->modelClass)) {
            throw new QueryException('Cannot create model instance, the assigned model does not exist.', QueryException::ERR_INVALID_MODEL);
        }

        return Structure::of($this->modelClass)
            ->createInstance($result);
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
     * Disable eager loading for the given relationships.
     *
     * @param string[] $relationships
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function eagerLoadDisable(array $relationships): void
    {
        $this->eagerLoadDisable = $relationships;
    }

    /**
     * Fetches a single row.
     *
     * @param int $fetchMode
     *
     * @return Model|stdClass|array|null
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
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
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
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
     * Fetches a single column of a single row.
     *
     * @param int $index
     *
     * @return mixed
     * @throws ExecutionException
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public final function fetchColumn(int $index = 0): mixed
    {
        $this->execute();

        return $this->pdoStatement->fetchColumn($index);
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
     * @throws ExecutionException
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private function execute(): void
    {
        if ($this->modelClass === null && !empty($this->eagerLoad)) {
            throw new QueryException('Eager loading is only available for models.', QueryException::ERR_EAGER_NOT_AVAILABLE);
        }

        if ($this->connection->logger->isEnabled()) {
            $stopwatch = new Stopwatch(__METHOD__);
            $result = $stopwatch->run($this->pdoStatement->execute(...));

            $this->connection->logger->log(new QueryEvent($this->pdoStatement->queryString, $stopwatch));
        } else {
            $result = $this->pdoStatement->execute();
        }

        if ($result === false) {
            [, $code, $message] = $this->pdoStatement->errorInfo();

            throw ExecutionException::of($code, $message);
        }
    }

    /**
     * Eager loads the relationships.
     *
     * @param Model|array $instances
     *
     * @throws ExecutionException
     * @throws ConnectionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private function loadRelationships(Model|array $instances): void
    {
        if (!is_array($instances)) {
            $instances = [$instances];
        }

        if (empty($instances)) {
            return;
        }

        $structure = Structure::of($this->modelClass);
        $structure->eagerLoadRelations($instances, $this->eagerLoad, $this->eagerLoadDisable);
    }

}
