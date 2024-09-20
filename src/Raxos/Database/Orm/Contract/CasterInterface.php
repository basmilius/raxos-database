<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Contract;

use Raxos\Database\Orm\Error\CasterException;
use Raxos\Database\Orm\Model;

/**
 * Interface CasterInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Contract
 * @since 1.0.17
 */
interface CasterInterface
{

    /**
     * Decodes the given datbase-allowed value into something else.
     *
     * @param string|float|int|null $value
     * @param Model $instance
     *
     * @return mixed
     * @throws CasterException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function decode(string|float|int|null $value, Model $instance): mixed;

    /**
     * Encodes the given value back into a database-allowed value.
     *
     * @param mixed $value
     * @param Model $instance
     *
     * @return string|float|int|null
     * @throws CasterException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function encode(mixed $value, Model $instance): string|float|int|null;

}
