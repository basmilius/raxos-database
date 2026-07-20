<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use Generator;
use PDO;
use PDOException;
use PDOStatement;
use Raxos\Collection\{ArrayList, Paginated};
use Raxos\Contract\Collection\ArrayListInterface;
use Raxos\Contract\Database\{ConnectionInterface, DatabaseExceptionInterface};
use Raxos\Contract\Database\Orm\{OrmExceptionInterface, PrimerTiming};
use Raxos\Contract\Database\Query\{InternalQueryInterface, QueryExceptionInterface, QueryInterface, StatementInterface};
use Raxos\Database\Error\{ExecutionException, NotConnectedException};
use Raxos\Database\Logger\QueryEvent;
use Raxos\Database\Orm\{Model, ModelArrayList};
use Raxos\Database\Orm\Structure\StructureGenerator;
use Raxos\Database\Query\Error\{ConnectionErrorException, InvalidModelException, MissingModelException, SyntaxException, UnexpectedException};
use Raxos\Foundation\Util\Stopwatch;
use stdClass;
use Throwable;
use function array_map;
use function ceil;
use function class_exists;
use function error_log;
use function floor;
use function is_array;
use function is_bool;
use function is_int;
use function sprintf;

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
     * @throws QueryExceptionInterface
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

        try {
            $this->pdoStatement = $connection->pdo->prepare($this->sql, $options);
        } catch (PDOException $err) {
            match ($err->getCode()) {
                'HY000' => throw new ConnectionErrorException(new NotConnectedException($err)),
                '42000' => throw new SyntaxException($this->sql, $err),
                default => throw new UnexpectedException($err)
            };
        }
    }

    /**
     * Statement destructor.
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __destruct()
    {
        try {
            $this->pdoStatement->closeCursor();
        } catch (Throwable $err) {
            // note(Bas): destructors must not throw. We intentionally swallow
            //  but emit to error_log() so cursor-cleanup failures (which can
            //  cause connection-pool exhaustion under load) leave a trail.
            error_log(sprintf(
                '[raxos/database] Statement::__destruct() closeCursor failed: %s (%s)',
                $err->getMessage(),
                $err::class
            ));
        }
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
    public final function arrayList(int $fetchMode = PDO::FETCH_ASSOC): ArrayListInterface|ModelArrayList
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

        $page = $limit > 0 ? (int)floor($offset / $limit) + 1 : 1;
        $pages = $limit > 0 ? (int)ceil($total / $limit) : 1;

        return new Paginated($items, $page, $limit, $pages, $total);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public final function run(): int
    {
        $this->execute();

        return $this->pdoStatement->rowCount();
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
    public final function bind(string $name, bool|string|int|float|null $value, ?int $type = null): static
    {
        $type ??= match (true) {
            is_bool($value) => PDO::PARAM_BOOL,
            is_int($value) => PDO::PARAM_INT,
            $value === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR
        };

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
            throw new InvalidModelException('Cannot create model instance, no model was assigned to the query.');
        }

        if (!is_array($result)) {
            throw new InvalidModelException('Cannot create model instance, the record set must be an array.');
        }

        if (!class_exists($this->modelClass)) {
            throw new InvalidModelException('Cannot create model instance, the assigned model class does not exist.');
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
     * @throws DatabaseExceptionInterface
     * @throws QueryExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private function execute(): void
    {
        if ($this->modelClass === null && !empty($this->eagerLoad)) {
            throw new MissingModelException();
        }

        if ($this->connection->logger->enabled) {
            $stopwatch = new Stopwatch(__METHOD__);
            $result = $stopwatch->run($this->pdoStatement->execute(...));

            $this->connection->logger->log(new QueryEvent($this->query, $stopwatch));
        } else {
            $result = $this->pdoStatement->execute();
        }

        if ($result === false) {
            [, $code, $message] = $this->pdoStatement->errorInfo();
            throw new ExecutionException($code, $message);
        }
    }

    /**
     * Eager loads the relationships.
     *
     * @param Model|array $instances
     *
     * @throws DatabaseExceptionInterface
     * @throws OrmExceptionInterface
     * @throws QueryExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private function loadRelationships(Model|array $instances): void
    {
        $list = $instances instanceof Model
            ? new ModelArrayList([$instances])
            : new ModelArrayList($instances);

        if ($list->isEmpty()) {
            return;
        }

        if ($this->query instanceof InternalQueryInterface) {
            $this->query->invokeBeforeRelationsHook($list);
            $this->query->invokePrimers($list, PrimerTiming::BeforeRelations, $this->connection);
        }

        $structure = StructureGenerator::for($this->modelClass);
        $structure->eagerLoadRelations($list, $this->eagerLoad, $this->eagerLoadDisable);

        if ($this->query instanceof InternalQueryInterface) {
            $this->query->invokePrimers($list, PrimerTiming::AfterRelations, $this->connection);
        }
    }

}
