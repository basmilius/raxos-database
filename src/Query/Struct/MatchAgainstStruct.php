<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use Raxos\Database\Contract\{ConnectionInterface, GrammarInterface, QueryInterface, QueryLiteralInterface, QueryStructInterface};
use Raxos\Database\Query\StructHelper;
use Raxos\Foundation\Contract\ArrayableInterface;
use Stringable;
use function array_map;
use function implode;
use function is_array;

/**
 * Class MatchAgainstStruct
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 2.0.0
 */
final readonly class MatchAgainstStruct implements QueryStructInterface
{

    /**
     * MatchAgainstStruct constructor.
     *
     * @param QueryLiteralInterface|QueryStructInterface|Stringable|ArrayableInterface<QueryInterface|QueryLiteralInterface|Stringable|string|float|int>|string|float|int|array<QueryInterface|QueryLiteralInterface|Stringable|string|float|int> $fields
     * @param QueryLiteralInterface|QueryStructInterface|Stringable|string|float|int $expression
     * @param bool $booleanMode
     * @param bool $queryExpansion
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public QueryLiteralInterface|QueryStructInterface|Stringable|ArrayableInterface|string|float|int|array $fields,
        public QueryLiteralInterface|QueryStructInterface|Stringable|string|float|int $expression,
        public bool $booleanMode = false,
        public bool $queryExpansion = false
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $fields = $this->fields;
        $expression = $this->expression;

        if ($fields instanceof ArrayableInterface) {
            $fields = $fields->toArray();
        } elseif (!is_array($fields)) {
            $fields = [$fields];
        }

        $fields = array_map(static fn(mixed $value) => StructHelper::compileValue($connection, $value), $fields);
        $fields = implode(', ', $fields);

        $expression = StructHelper::compileValue($connection, $expression);

        if ($this->booleanMode) {
            $expression .= ' in boolean mode';
        }

        if ($this->queryExpansion) {
            $expression .= ' with query expansion';
        }

        $query->raw("match({$fields}) against ({$expression})");
    }

}
