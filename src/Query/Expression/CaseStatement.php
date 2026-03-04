<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use Raxos\Contract\Database\{ConnectionInterface, GrammarInterface};
use Raxos\Contract\Database\Query\{QueryExpressionInterface, QueryInterface, QueryValueInterface};

/**
 * Class CaseStatement
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Expression
 * @since 2.1.0
 */
final class CaseStatement implements QueryExpressionInterface
{

    /** @var When[] */
    private array $whens = [];
    private QueryExpressionInterface|QueryValueInterface|null $elseResult = null;

    /**
     * Adds a WHEN condition with its THEN result to the CASE expression.
     *
     * @param QueryExpressionInterface $condition
     * @param QueryExpressionInterface|QueryValueInterface $then
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 2.1.0
     */
    public function when(
        QueryExpressionInterface $condition,
        QueryExpressionInterface|QueryValueInterface $then
    ): static
    {
        $this->whens[] = new When($condition, $then);

        return $this;
    }

    /**
     * Sets the ELSE result for the CASE expression.
     *
     * @param QueryExpressionInterface|QueryValueInterface $result
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 2.1.0
     */
    public function else(
        QueryExpressionInterface|QueryValueInterface $result
    ): static
    {
        $this->elseResult = $result;

        return $this;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.1.0
     */
    public function compile(QueryInterface $query, ConnectionInterface $connection, GrammarInterface $grammar): void
    {
        $query->raw('case ');

        foreach ($this->whens as $when) {
            $when->compile($query, $connection, $grammar);
            $query->raw(' ');
        }

        if ($this->elseResult !== null) {
            $query->raw('else ');
            $query->compile($this->elseResult);
            $query->raw(' ');
        }

        $query->raw('end');
    }

}
