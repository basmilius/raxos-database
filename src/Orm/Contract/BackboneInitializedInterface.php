<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Contract;

use Raxos\Database\Orm\Error\StructureException;

/**
 * Interface BackboneInitializedInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Contract
 * @since 1.5.0
 */
interface BackboneInitializedInterface
{

    /**
     * Triggered just after the backbone instance is created.
     *
     * @param BackboneInterface<static>&AccessInterface $backbone
     * @param array $data
     *
     * @return void
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public static function onBackboneInitialized(BackboneInterface&AccessInterface $backbone, array $data): void;

}
