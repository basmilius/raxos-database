<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

/**
 * Class Piece
 *
 * Represents a single SQL fragment within a query builder's internal piece list.
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 2.1.0
 */
final readonly class Piece
{

    /**
     * Piece constructor.
     *
     * @param string $clause
     * @param string|array|int|null $data
     * @param string|null $separator
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.1.0
     */
    public function __construct(
        public string $clause,
        public string|array|int|null $data = null,
        public ?string $separator = null
    ) {}

}
