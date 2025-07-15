<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Contract;

use Raxos\Database\Orm\Definition\PropertyDefinition;

/**
 * Interface MutationListenerInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Contract
 * @since 1.1.0
 */
interface MutationListenerInterface
{

    /**
     * This function is invoked when a mutation within the model happens. This is
     * only tried for mutations that are done on properties that are tracked by the
     * database model.
     *
     * @param PropertyDefinition $property
     * @param mixed $newValue
     * @param mixed $oldValue
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function onMutation(PropertyDefinition $property, mixed $newValue, mixed $oldValue): void;

}
