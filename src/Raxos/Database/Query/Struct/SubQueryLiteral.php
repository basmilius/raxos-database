<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use Raxos\Database\Query\QueryBaseInterface;

/**
 * Class SubQueryLiteral
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 1.0.0
 */
readonly class SubQueryLiteral extends ComparatorAwareLiteral implements AfterExpressionInterface
{

    /**
     * SubQueryLiteral constructor.
     *
     * @param QueryBaseInterface $query
     * @param string $clause
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(protected QueryBaseInterface $query, protected string $clause = '')
    {
        parent::__construct('');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function after(QueryBaseInterface $query): void
    {
        if (!empty($this->clause)) {
            $query->addPiece($this->clause);
        }

        $query->parenthesis(fn() => $query->merge($this->query), false);
    }

    /**
     * Returns a `exists $query` literal.
     *
     * @param QueryBaseInterface $query
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function exists(QueryBaseInterface $query): self
    {
        return new self($query, 'exists');
    }

    /**
     * Returns a `not exists $query` literal.
     *
     * @param QueryBaseInterface $query
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function notExists(QueryBaseInterface $query): self
    {
        return new self($query, 'not exists');
    }

    /**
     * Returns a `($query)` literal.
     *
     * @param QueryBaseInterface $query
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function of(QueryBaseInterface $query): self
    {
        return new self($query);
    }

    /**
     * Returns a `@$name := ($query)` literal.
     *
     * @param string $name
     * @param QueryBaseInterface $query
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function variable(string $name, QueryBaseInterface $query): self
    {
        return new self($query, "@{$name} := ");
    }

}
