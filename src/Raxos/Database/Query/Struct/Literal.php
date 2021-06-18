<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use Raxos\Database\Query\QueryBase;
use Stringable;
use function addslashes;

/**
 * Class Literal
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 1.0.0
 */
class Literal extends Value implements Stringable
{

    /**
     * Literal constructor.
     *
     * @param string|int|float $value
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(protected string|int|float $value)
    {
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function get(QueryBase $query): string|int|float
    {
        return $this->value;
    }

    /**
     * Returns a `'$str'` literal.
     *
     * @param string $str
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function string(string $str): self
    {
        $str = addslashes($str);

        return new Literal("'{$str}'");
    }

    /**
     * Returns a `$value` literal.
     *
     * @param string|int|float|bool $value
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function with(string|int|float|bool $value): self
    {
        return new Literal($value);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __toString(): string
    {
        return (string)$this->value;
    }

}
