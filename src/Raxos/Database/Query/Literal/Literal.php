<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Literal;

use Raxos\Database\Contract\QueryLiteralInterface;
use Stringable;
use function addslashes;

/**
 * Class Literal
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Literal
 * @since 1.5.0
 */
final readonly class Literal implements QueryLiteralInterface
{

    /**
     * Literal constructor.
     *
     * @param Stringable|string|int|float|bool|null $value
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public function __construct(
        public Stringable|string|int|float|bool|null $value
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public function __toString(): string
    {
        return (string)$this->value;
    }

    /**
     * Returns a `$value` literal.
     *
     * @param Stringable|string|int|float|bool|null $value
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public static function of(
        Stringable|string|int|float|bool|null $value
    ): self
    {
        return new self($value);
    }

    /**
     * Returns a `'$str'` literal.
     *
     * @param string $str
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public static function string(string $str): self
    {
        $str = addslashes($str);

        return new self("'{$str}'");
    }

}
