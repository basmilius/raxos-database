<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

/**
 * Enum SortDirection
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 2.1.0
 */
enum SortDirection: string
{

    case ASC = 'asc';
    case DESC = 'desc';

}
