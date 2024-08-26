<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use BackedEnum;
use Closure;
use Generator;
use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;
use PDO;
use Raxos\Database\Connection\Connection;
use Raxos\Database\Dialect\Dialect;
use Raxos\Database\Error\{ConnectionException, QueryException};
use Raxos\Database\Orm\{Model, ModelArrayList};
use Raxos\Database\Query\Struct\{AfterExpressionInterface, BeforeExpressionInterface, ColumnLiteral, ComparatorAwareLiteral, Literal, ValueInterface};
use Raxos\Foundation\Collection\ArrayList;
use Raxos\Foundation\Contract\DebuggableInterface;
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

/**
 * Class QueryBase
 *
 * @template TModel of Model
 * @implements QueryBaseInterface<TModel>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 1.0.0
 */
abstract class QueryBase implements DebuggableInterface, InternalQueryInterface, JsonSerializable, QueryBaseInterface, Stringable
{

    private static int $index = 0;

    public readonly Dialect $dialect;

    protected string $currentClause = '';
    /** @var class-string<Model>|null */
    protected ?string $modelClass = null;
    protected array $pieces = [];

    private array $eagerLoad = [];
    private array $eagerLoadDisable = [];
    private array $params = [];
    private readonly int $paramsIndex;

    // internal
    private ?Closure $beforeRelations = null;

    /**
     * QueryBase constructor.
     *
     * @param Connection $connection
     * @param bool $prepared
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        public readonly Connection $connection,
        public readonly bool $prepared = true
    )
    {
        $this->dialect = $connection->dialect;
        $this->paramsIndex = ++self::$index;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function addExpression(
        string $clause,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $lhs = null,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $cmp = null,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $rhs = null
    ): static
    {
        if ($rhs === null && $cmp !== null) {
            $rhs = $cmp;
            $cmp = '=';
        }

        if ($lhs instanceof BackedEnum) {
            $lhs = is_string($lhs->value) ? stringLiteral($lhs->value) : literal($lhs);
        }

        if ($rhs instanceof BackedEnum) {
            $rhs = is_string($rhs->value) ? stringLiteral($rhs->value) : literal($rhs);
        }

        if ($rhs instanceof ValueInterface) {
            if ($rhs instanceof ComparatorAwareLiteral) {
                $cmp = null;
            }

            $rhs = $rhs->get($this);
        } elseif ($cmp !== null) {
            $rhs = $this->addParam($rhs);
        }

        if ($lhs !== null && !($lhs instanceof ComparatorAwareLiteral)) {
            if (is_string($lhs)) {
                $lhs = $this->dialect->escapeFields($lhs);
            } elseif ($lhs instanceof ValueInterface) {
                $lhs = $lhs->get($this);
            }

            if ($cmp === null && $rhs !== null) {
                $expression = "{$lhs} {$rhs}";
            } elseif ($cmp === null) {
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
    public function addParam(BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $value): string|int
    {
        if ($value instanceof Literal) {
            return (string)$value;
        }

        if ($value instanceof ValueInterface) {
            $value = $value->get($this);
        }

        if (!$this->prepared) {
            if (is_int($value)) {
                return $value;
            }

            try {
                return $this->connection->quote((string)$value);
            } catch (ConnectionException $err) {
                throw QueryException::connection($err);
            }
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if ($value instanceof Stringable) {
            $value = (string)$value;
        }

        $name = $this->paramsIndex . '_' . count($this->params);
        $this->params[] = [$name, $value];

        return ':' . $name;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function addPiece(string $clause, ColumnLiteral|array|string|int|null $data = null, ?string $separator = null): static
    {
        if ($data instanceof ColumnLiteral) {
            $data = (string)$data;
        }

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

            return $this;
        }

        foreach ($relations as $relation) {
            $this->eagerLoad[] = $relation;
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

            return $this;
        }

        foreach ($relations as $relation) {
            $this->eagerLoadDisable[] = $relation;
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
    public function merge(QueryBaseInterface $query): static
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
    public function parenthesisOpen(
        ?string $lhs = null,
        ?string $cmp = null,
        BackedEnum|Stringable|ValueInterface|string|int|float|bool|null $rhs = null
    ): static
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

        return in_array($clause, $clauses, true);
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
        $index = ArrayUtil::findIndex($this->pieces, static fn(array $piece) => $piece[0] === $clause);

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
        $index = ArrayUtil::findIndex($this->pieces, static fn(array $piece) => $piece[0] === $clause);

        if ($index === null) {
            throw QueryException::missingClause($clause);
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

            if (!empty($clause)) {
                $pieces[] = $clause;
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

        return (int)$query
            ->statement()
            ->fetchColumn();
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
            $original->replaceClause('select', static function (array $piece): array {
                $piece[1] = '*';

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

        return (int)$query
            ->statement()
            ->fetchColumn();
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
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function runReturning(array|Literal|string $column): array|string|int
    {
        $statement = $this
            ->addPiece('returning', $column, $this->dialect->fieldSeparator)
            ->statement();

        $statement->run();

        if (!is_array($column)) {
            return $statement->pdoStatement->fetchColumn();
        }

        $data = $statement->pdoStatement->fetch(PDO::FETCH_ASSOC);
        $result = [];

        foreach ($column as $col) {
            if ($col instanceof ColumnLiteral) {
                $result[$col->column] = $data[$col->column];
            } else {
                $result[$col] = $data[$col];
            }
        }

        return $result;
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
            throw QueryException::missingResult();
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@glybe.nl>
     * @since 1.0.0
     */
    public function statement(array $options = []): StatementInterface
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
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function withQuery(callable $fn): static
    {
        return $fn($this);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function _internal_beforeRelations(callable $fn): QueryBaseInterface
    {
        $this->beforeRelations = $fn;

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function _internal_invokeBeforeRelations(array $instances): void
    {
        if ($this->beforeRelations === null) {
            return;
        }

        $beforeRelations = $this->beforeRelations;
        $beforeRelations($instances);
    }

    /**
     * Resets the builder.
     *
     * @return static<TModel>
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
     * @since 1.0.16
     */
    public function jsonSerialize(): string
    {
        return $this->toSql();
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
            'type' => $this->prepared ? 'PREPARED QUERY' : 'RAW QUERY',
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
