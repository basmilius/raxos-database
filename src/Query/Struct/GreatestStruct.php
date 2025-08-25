<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use BackedEnum;
use Raxos\Database\Contract\QueryValueInterface;
use Stringable;

/**
 * Class FunctionStruct
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 2.0.0
 */
final readonly class GreatestStruct extends FunctionStruct
{

    /**
     * FunctionStruct constructor.
     *
     * @param array<BackedEnum|Stringable|QueryValueInterface|string|int|float|bool> $params
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(array $params = [])
    {
        parent::__construct('greatest', $params);
    }

}
