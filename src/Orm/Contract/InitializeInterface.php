<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Contract;

/**
 * Interface InitializeInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Contract
 * @since 1.3.0
 */
interface InitializeInterface
{

    /**
     * Triggered just before initializing a model.
     *
     * @param array $data
     *
     * @return array
     * @author Bas Milius <bas@mili.us>
     * @since 1.3.0
     */
    public static function onInitialize(array $data): array;

}
