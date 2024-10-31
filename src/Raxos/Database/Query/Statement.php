<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use Generator;
use PDO;
use PDOStatement;
use Raxos\Database\Contract\{ConnectionInterface, InternalQueryInterface, QueryInterface, StatementInterface};
use Raxos\Database\Error\{ConnectionException, ExecutionException, QueryException};
use Raxos\Database\Logger\QueryEvent;
use Raxos\Database\Orm\{Model, ModelArrayList};
use Raxos\Database\Orm\Error\{RelationException, StructureException};
use Raxos\Database\Orm\Structure\StructureGenerator;
use Raxos\Foundation\Collection\{ArrayList, Paginated};
use Raxos\Foundation\Util\Stopwatch;
use stdClass;
use function array_map;
use function ceil;
use function class_exists;
use function floor;
use function is_array;
use function is_int;

/**
 * Class Statement
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 1.0.0
 */
class Statement implements StatementInterface
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
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public final function array(int $fetchMode = PDO::FETCH_ASSOC): array
    {
        $this->execute();

        return $this->fetchAll($fetchMode);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public final function arrayList(int $fetchMode = PDO::FETCH_ASSOC): ArrayList|ModelArrayList
    {
        $results = $this->array($fetchMode);

        if ($this->modelClass !== null) {
            return new ModelArrayList($results);
        }

        return new ArrayList($results);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public final function cursor(int $fetchMode = PDO::FETCH_ASSOC): Generator
    {
        $this->execute();

        while ($result = $this->fetch($fetchMode)) {
            yield $result;
        }
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.3.1
     */
    public final function paginate(int $offset, int $limit, ?callable $itemBuilder = null, ?callable $totalBuilder = null, int $fetchMode = PDO::FETCH_ASSOC): Paginated
    {
        $itemBuilder ??= static fn(QueryInterface $query, int $offset, int $limit) => $query->limit($limit, $offset)->arrayList();
        $totalBuilder ??= static fn(QueryInterface $query, int $offset, int $limit) => $query->totalCount();

        $items = $itemBuilder($this->query, $offset, $limit);
        $total = $totalBuilder($this->query, $offset, $limit);

        $page = (int)floor($offset / $limit) + 1;
        $pages = (int)ceil($total / $limit);

        return new Paginated($items, $page, $limit, $pages, $total);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public final function run(): void
    {
        $this->execute();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public final function single(int $fetchMode = PDO::FETCH_ASSOC): Model|stdClass|array|null
    {
        $this->execute();

        return $this->fetch($fetchMode);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public final function bind(string $name, string|int|float|null $value, ?int $type = null): static
    {
        $type ??= is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;

        $this->pdoStatement->bindValue($name, $value, $type);

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public final function createModel(mixed $result): Model
    {
        if ($this->modelClass === null) {
            throw QueryException::invalidModel('Cannot create model instance, no model was assigned to the query.');
        }

        if (!is_array($result)) {
            throw QueryException::invalidModel('Cannot create model instance, the record set must be an array.');
        }

        if (!class_exists($this->modelClass)) {
            throw QueryException::invalidModel('Cannot create model instance, the assigned model class does not exist.');
        }

        return StructureGenerator::for($this->modelClass)
            ->createInstance($result);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public final function eagerLoad(array $relationships): void
    {
        $this->eagerLoad = $relationships;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public final function eagerLoadDisable(array $relationships): void
    {
        $this->eagerLoadDisable = $relationships;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
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
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public final function fetchAll(int $fetchMode = PDO::FETCH_ASSOC): array
    {
        $results = $this->pdoStatement->fetchAll($fetchMode);

        if ($this->modelClass !== null) {
            $models = array_map($this->createModel(...), $results);

            $this->loadRelationships($models);

            return $models;
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public final function fetchColumn(int $index = 0): mixed
    {
        $this->execute();

        return $this->pdoStatement->fetchColumn($index);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public final function rowCount(): int
    {
        return $this->pdoStatement->rowCount();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public final function withModel(string $class): static
    {
        $this->modelClass = $class;

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
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
            throw QueryException::missingModel();
        }

        if ($this->connection->logger->isEnabled()) {
            $stopwatch = new Stopwatch(__METHOD__);
            $result = $stopwatch->run($this->pdoStatement->execute(...));

            $this->connection->logger->log(new QueryEvent($this->query, $stopwatch));
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

        if ($this->query instanceof InternalQueryInterface) {
            $this->query->_internal_invokeBeforeRelations($instances);
        }

        if (empty($instances)) {
            return;
        }

        $structure = StructureGenerator::for($this->modelClass);
        $structure->eagerLoadRelations($instances, $this->eagerLoad, $this->eagerLoadDisable);
    }

}
