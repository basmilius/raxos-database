<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use BackedEnum;
use Raxos\Contract\Database\ConnectionInterface;
use Raxos\Contract\Database\GrammarInterface;
use Raxos\Contract\Database\Query\QueryExpressionInterface;
use Raxos\Contract\Database\Query\QueryInterface;
use Raxos\Contract\Database\Query\QueryValueInterface;
use Raxos\Database\Grammar\SQLiteGrammar;
use Stringable;
use function strtolower;

/**
 * Class Func
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Expression
 * @since 2.0.0
 */
final readonly class Func implements QueryExpressionInterface
{

    /**
     * Func constructor.
     *
     * @param string $name
     * @param iterable<BackedEnum|QueryValueInterface|Stringable|string|int|float|bool> $params
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public string $name,
        public iterable $params = []
    ) {}

    /**
     * Uses SQLite's scalar min/max spelling for MySQL-style least/greatest expressions.
     *
     * @param QueryInterface $query
     * @param ConnectionInterface $connection
     * @param GrammarInterface $grammar
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function compile(
        QueryInterface $query,
        ConnectionInterface $connection,
        GrammarInterface $grammar
    ): void
    {
        $name = $grammar instanceof SQLiteGrammar
            ? match (strtolower($this->name)) {
                'least' => 'min',
                'greatest' => 'max',
                default => $this->name
            }
            : $this->name;

        $query->raw("{$name}(");
        $query->compileMultiple($this->params);
        $query->raw(')');
    }

}
