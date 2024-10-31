<?php
declare(strict_types=1);

namespace Raxos\Database\Contract;

use Generator;
use PDO;
use Raxos\Database\Error\{ConnectionException, ExecutionException, QueryException};
use Raxos\Database\Orm\{Model, ModelArrayList};
use Raxos\Foundation\Collection\Paginated;
use Raxos\Foundation\Contract\ArrayListInterface;
use Raxos\Database\Orm\Error\{RelationException, StructureException};
use Raxos\Foundation\Collection\ArrayList;
use stdClass;

/**
 * Interface StatementInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Contract
 * @since 1.0.17
 */
interface StatementInterface
{

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
    public function array(int $fetchMode = PDO::FETCH_ASSOC): array;

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
    public function arrayList(int $fetchMode = PDO::FETCH_ASSOC): ArrayList|ModelArrayList;

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
    public function cursor(int $fetchMode = PDO::FETCH_ASSOC): Generator;

    /**
     * Executes the statement and returns a paginated response.
     *
     * @param int $offset
     * @param int $limit
     * @param callable(QueryInterface, int, int):ArrayListInterface|null $itemBuilder
     * @param callable(QueryInterface, int, int):int|null $totalBuilder
     * @param int $fetchMode
     *
     * @return Paginated
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.3.1
     * @see StatementInterface::arrayList()
     */
    public function paginate(int $offset, int $limit, ?callable $itemBuilder = null, ?callable $totalBuilder = null, int $fetchMode = PDO::FETCH_ASSOC): Paginated;

    /**
     * Executes the statement.
     *
     * @throws ExecutionException
     * @throws QueryException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function run(): void;

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
    public function single(int $fetchMode = PDO::FETCH_ASSOC): Model|stdClass|array|null;

    /**
     * Binds the given value.
     *
     * @param string $name
     * @param string|int|float|null $value
     * @param int|null $type
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function bind(string $name, string|int|float|null $value, ?int $type = null): self;

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
    public function createModel(mixed $result): Model;

    /**
     * Enable eager loading for the given relationships.
     *
     * @param string[] $relationships
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function eagerLoad(array $relationships): void;

    /**
     * Disable eager loading for the given relationships.
     *
     * @param string[] $relationships
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function eagerLoadDisable(array $relationships): void;

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
    public function fetch(int $fetchMode = PDO::FETCH_ASSOC): Model|stdClass|array|null;

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
    public function fetchAll(int $fetchMode = PDO::FETCH_ASSOC): array;

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
    public function fetchColumn(int $index = 0): mixed;

    /**
     * Returns the number of rows in the result.
     *
     * @return int
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function rowCount(): int;

    /**
     * Associates a model.
     *
     * @param string $class
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function withModel(string $class): self;

    /**
     * Removes the associated model.
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function withoutModel(): self;

}
