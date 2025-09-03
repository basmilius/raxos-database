<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use BackedEnum;
use Raxos\Database\Contract\QueryValueInterface;
use Stringable;

/**
 * Class ConcatExpression
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Expression
 * @since 2.0.0
 */
final readonly class ConcatExpression extends FunctionExpression
{

    /**
     * ConcatExpression constructor.
     *
     * @param iterable<BackedEnum|Stringable|QueryValueInterface|string|int|float|bool> $params
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(iterable $params = [])
    {
        parent::__construct('concat', $params);
    }

}
