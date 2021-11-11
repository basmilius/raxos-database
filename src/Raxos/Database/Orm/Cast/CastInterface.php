<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Cast;

/**
 * Interface CastInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Cast
 * @since 1.0.0
 */
interface CastInterface
{

    /**
     * Decodes the given value from database-allowed data.
     *
     * @param string|float|int|null $value
     *
     * @return mixed
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function decode(string|float|int|null $value): mixed;

    /**
     * Encodes the given value to database-allowed data.
     *
     * @param mixed $value
     *
     * @return string|float|int|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function encode(mixed $value): string|float|int|null;

}
