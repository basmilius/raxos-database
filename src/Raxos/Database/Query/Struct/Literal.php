<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use JetBrains\PhpStorm\Pure;
use Raxos\Database\Query\QueryBaseInterface;
use Stringable;
use function addslashes;

/**
 * Class Literal
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 1.0.0
 */
readonly class Literal implements ValueInterface
{

    /**
     * Literal constructor.
     *
     * @param Stringable|string|int|float $value
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        protected Stringable|string|int|float $value
    )
    {
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function get(QueryBaseInterface $query): string|int|float
    {
        if ($this->value instanceof Stringable) {
            return (string)$this->value;
        }

        return $this->value;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function jsonSerialize(): string
    {
        return (string)$this;
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

    /**
     * Returns a `'$str'` literal.
     *
     * @param string $str
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    public static function string(string $str): self
    {
        $str = addslashes($str);

        return new Literal("'{$str}'");
    }

    /**
     * Returns a `$value` literal.
     *
     * @param Stringable|string|int|float|bool $value
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    public static function with(Stringable|string|int|float|bool $value): self
    {
        return new Literal($value);
    }

}
