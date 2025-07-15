<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Error;

use Raxos\Foundation\Error\ExceptionId;

/**
 * Class CasterException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Error
 * @since 1.0.17
 */
final class CasterException extends OrmException
{

    /**
     * Returns a bail exception.
     *
     * @param string $message
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function bail(string $message): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_orm_caster_bail',
            $message
        );
    }

}
