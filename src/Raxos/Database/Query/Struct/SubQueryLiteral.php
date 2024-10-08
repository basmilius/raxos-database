<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use Raxos\Database\Contract\{AfterQueryExpressionInterface, QueryInterface};

/**
 * Class SubQueryLiteral
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 1.0.0
 */
readonly class SubQueryLiteral extends ComparatorAwareLiteral implements AfterQueryExpressionInterface
{

    /**
     * SubQueryLiteral constructor.
     *
     * @param QueryInterface $query
     * @param string $clause
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        protected QueryInterface $query,
        protected string $clause = ''
    )
    {
        parent::__construct('');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function after(QueryInterface $query): void
    {
        if (!empty($this->clause)) {
            $query->addPiece($this->clause);
        }

        $query->parenthesis(fn() => $query->merge($this->query), false);
    }

    /**
     * Returns a `exists $query` literal.
     *
     * @param QueryInterface $query
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function exists(QueryInterface $query): self
    {
        return new self($query, 'exists');
    }

    /**
     * Returns a `not exists $query` literal.
     *
     * @param QueryInterface $query
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function notExists(QueryInterface $query): self
    {
        return new self($query, 'not exists');
    }

    /**
     * Returns a `($query)` literal.
     *
     * @param QueryInterface $query
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function of(QueryInterface $query): self
    {
        return new self($query);
    }

    /**
     * Returns a `@$name := ($query)` literal.
     *
     * @param string $name
     * @param QueryInterface $query
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function variable(string $name, QueryInterface $query): self
    {
        return new self($query, "@{$name} := ");
    }

}
