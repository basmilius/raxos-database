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
use stdClass;
use function array_map;
use function is_int;
use function str_contains;

/**
 * Class Statement
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 1.0.0
 */
class Statement
{

    // todo(Bas): arrayList()
    // todo(Bas): eager loading when models are implemented

    private array $eagerLoad = [];
    private PDOStatement $pdoStatement;
    private string $sql;

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
     * @param int|null $foundRows
     *
     * @return array
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function array(int $fetchMode = PDO::FETCH_ASSOC, ?int $foundRows = null): array
    {
        $this->execute($foundRows);

        return $this->fetchAll($fetchMode);
    }

    /**
     * Executes the statement and returns a generator containing all results.
     *
     * @param int $fetchMode
     * @param int|null $foundRows
     *
     * @return Generator
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function cursor(int $fetchMode = PDO::FETCH_ASSOC, ?int $foundRows = null): Generator
    {
        $this->execute($foundRows);

        while ($result = $this->fetch($fetchMode)) {
            yield $result;
        }
    }

    /**
     * Executes the statement.
     *
     * @param int|null $foundRows
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function run(?int $foundRows = null): void
    {
        $this->execute($foundRows);
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
     * @param string|int|float $value
     * @param int|null $type
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function bind(string $name, string|int|float $value, ?int $type = null): static
    {
        $type ??= is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;

        $this->pdoStatement->bindValue($name, $value, $type);

        return $this;
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
            /** @var Model $model */
            $model = $this->modelClass;

            return new $model($result, false);
        }

        return $result;
    }

    /**
     * Fetches all rows.
     *
     * @param int $fetchMode
     *
     * @return array
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function fetchAll(int $fetchMode = PDO::FETCH_ASSOC): array
    {
        $results = $this->pdoStatement->fetchAll($fetchMode);

        if ($this->modelClass !== null) {
            /** @var Model $model */
            $model = $this->modelClass;

            // todo(Bas): eager loading

            return array_map(fn(mixed $result) => new $model($result, false), $results);
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
     * @param int|null $foundRows
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private function execute(?int &$foundRows = null): void
    {
        if ($this->modelClass === null && !empty($this->eagerLoad)) {
            throw new QueryException('Eager loading is only available for models.', QueryException::ERR_EAGER_NOT_AVAILABLE);
        }

        // todo(Bas): Query logging.

        $result = $this->pdoStatement->execute();

        if ($result === false) {
            throw $this->throwFromError();
        }

        $foundRows = str_contains($this->sql, 'sql_calc_found_rows') ? $this->connection->foundRows() : null;
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
