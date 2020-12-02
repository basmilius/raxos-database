<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use Raxos\Database\Query\QueryBase;

/**
 * Class SubQueryLiteral
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 1.0.0
 */
class SubQueryLiteral extends ComparatorAwareLiteral implements AfterExpressionInterface
{

    /**
     * SubQueryLiteral constructor.
     *
     * @param QueryBase $query
     * @param string $clause
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(protected QueryBase $query, protected string $clause = '')
    {
        parent::__construct('');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function after(QueryBase $query): void
    {
        if (!empty($this->clause)) {
            $query->addPiece($this->clause);
        }

        $query->parenthesis(fn() => $query->merge($this->query), false);
    }

    /**
     * Returns a `exists $query` literal.
     *
     * @param QueryBase $query
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function exists(QueryBase $query): self
    {
        return new self($query, 'exists');
    }

    /**
     * Returns a `not exists $query` literal.
     *
     * @param QueryBase $query
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function notExists(QueryBase $query): self
    {
        return new self($query, 'not exists');
    }

    /**
     * Returns a `($query)` literal.
     *
     * @param QueryBase $query
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function of(QueryBase $query): self
    {
        return new self($query);
    }

    /**
     * Returns a `@$name := ($query)` literal.
     *
     * @param string $name
     * @param QueryBase $query
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function variable(string $name, QueryBase $query): self
    {
        return new self($query, "@{$name} := ");
    }

}
