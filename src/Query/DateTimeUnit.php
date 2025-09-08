<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

/**
 * Enum DateTimeUnit
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 2.0.0
 */
enum DateTimeUnit: string
{
    case MICROSECOND = 'microsecond';
    case SECOND = 'second';
    case MINUTE = 'minute';
    case HOUR = 'hour';
    case DAY = 'day';
    case WEEK = 'week';
    case MONTH = 'month';
    case QUARTER = 'quarter';
    case YEAR = 'year';
}
