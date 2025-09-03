<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Expression;

use BackedEnum;
use Raxos\Database\Contract\QueryValueInterface;
use Stringable;
use function array_unshift;
use function is_array;
use function Raxos\Database\Query\stringLiteral;

/**
 * Class ConcatWsExpression
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Expression
 * @since 2.0.0
 */
final readonly class ConcatWsExpression extends FunctionExpression
{

    /**
     * ConcatWsExpression constructor.
     *
     * @param string $separator
     * @param iterable<BackedEnum|Stringable|QueryValueInterface|string|int|float|bool> $params
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(string $separator = ' ', iterable $params = [])
    {
        if (!is_array($params)) {
            $params = iterator_to_array($params);
        }

        array_unshift($params, stringLiteral($separator));

        parent::__construct('concat_ws', $params);
    }

}
