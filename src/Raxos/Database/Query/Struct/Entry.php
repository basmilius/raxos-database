<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use Raxos\Database\Query\QueryInterface;

/**
 * Class Entry
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 1.0.17
 */
final readonly class Entry
{

    /**
     * Entry constructor.
     *
     * @param QueryInterface|ValueInterface|string|int $value
     * @param string|null $alias
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public QueryInterface|ValueInterface|string|int $value,
        public ?string $alias = null
    ) {}

}
