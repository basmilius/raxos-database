<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use Generator;
use JetBrains\PhpStorm\ArrayShape;
use PDO;
use Raxos\Database\Connection\Connection;
use Raxos\Database\Dialect\Dialect;
use Raxos\Database\Error\QueryException;
use Raxos\Database\Orm\Model;
use Raxos\Database\Orm\ModelArrayList;
use Raxos\Database\Query\Struct\AfterExpressionInterface;
use Raxos\Database\Query\Struct\BeforeExpressionInterface;
use Raxos\Database\Query\Struct\ComparatorAwareLiteral;
use Raxos\Database\Query\Struct\Value;
use Raxos\Foundation\Collection\ArrayList;
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
 * @template TValue
 * @implements QueryBaseInterface<TValue>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 1.0.0
 */
abstract class QueryBase implements DebugInfoInterface, QueryBaseInterface, Stringable
{

    private static int $index = 0;
    private int $paramsIndex;

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
        $this->dialect = $connection->getDialect();
        $this->paramsIndex = ++self::$index;
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
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function addExpression(string $clause, Stringable|Value|string|int|float|bool|null $lhs = null, Stringable|Value|string|int|float|bool|null $cmp = null, Stringable|Value|string|int|float|bool|null $rhs = null): static
    {
        if ($rhs === null && $cmp !== null) {
            $rhs = $cmp;
            $cmp = '=';
        }

        if ($rhs instanceof Value) {
            if ($rhs instanceof ComparatorAwareLiteral) {
                $cmp = null;
            }

            $rhs = $rhs->get($this);
        } else if ($cmp !== null) {
            $rhs = $this->addParam($rhs);
        }

        if ($lhs !== null && !($lhs instanceof ComparatorAwareLiteral)) {
            if (is_string($lhs)) {
                $lhs = $this->dialect->escapeFields($lhs);
            } else if ($lhs instanceof Value) {
                $lhs = $lhs->get($this);
            }

            if ($cmp === null && $rhs !== null) {
                $expression = "{$lhs} {$rhs}";
            } else if ($cmp === null) {
                $expression = $lhs;
            } else {
                $expression = "{$lhs} {$cmp} {$rhs}";
            }
        } else {
            $expression = null;
        }

        $args = [$lhs, $cmp, $rhs];

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
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
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

        $name = 'p' . $this->paramsIndex . '_' . count($this->params);
        $this->params[] = [$name, $value];

        return ':' . $name;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
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
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
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
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
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
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
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
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
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
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function eagerLoadReset(): static
    {
        $this->eagerLoad = [];
        $this->eagerLoadDisable = [];

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
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
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
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
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function parenthesisClose(): static
    {
        return $this->addPiece(')');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function parenthesisOpen(?string $lhs = null, ?string $cmp = null, Stringable|Value|string|int|float|bool|null $rhs = null): static
    {
        return $this->addExpression('(', $lhs, $cmp, $rhs);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function raw(string $expression): static
    {
        return $this->addPiece($expression);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function isClauseDefined(string $clause): bool
    {
        $clauses = array_column($this->pieces, 0);

        return in_array($clause, $clauses);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function isModelQuery(): bool
    {
        return $this->modelClass !== null;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
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
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
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
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function withModel(string $class): static
    {
        $this->modelClass = $class;

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function withoutModel(): static
    {
        $this->modelClass = null;

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
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
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function resultCount(): int
    {
        $query = $this->connection
            ->query()
            ->select('count(*)')
            ->from($this, '__n__');

        $query->params = $this->params;

        $result = $query->single();

        return (int)$result['count(*)'];
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
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

        $query = $original->connection
            ->query()
            ->select('count(*)')
            ->from($original, '__n__');

        $query->params = $original->params;

        $result = $query->single();

        return (int)$result['count(*)'];
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function array(int $fetchMode = PDO::FETCH_ASSOC, array $options = []): array
    {
        return $this
            ->statement($options)
            ->array($fetchMode);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function arrayList(int $fetchMode = PDO::FETCH_ASSOC, array $options = []): ArrayList|ModelArrayList
    {
        return $this
            ->statement($options)
            ->arrayList($fetchMode);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function cursor(int $fetchMode = PDO::FETCH_ASSOC, array $options = []): Generator
    {
        return $this
            ->statement($options)
            ->cursor($fetchMode);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function run(array $options = []): void
    {
        $this
            ->statement($options)
            ->run();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function single(int $fetchMode = PDO::FETCH_ASSOC, array $options = []): Model|stdClass|array|null
    {
        return $this
            ->statement($options)
            ->single($fetchMode);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
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
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
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
     * @return static<TValue>
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
