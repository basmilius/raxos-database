<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

/**
 * Enum ModelEvent
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.2
 */
enum ModelEvent: string
{

    case CREATE = 'create';
    case DELETE = 'delete';
    case UPDATE = 'update';

}
