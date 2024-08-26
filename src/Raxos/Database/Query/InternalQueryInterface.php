<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use Raxos\Database\Orm\Model;

/**
 * Interface InternalQueryInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 1.1.0
 * @internal
 * @private
 */
interface InternalQueryInterface
{

    /**
     * The given function will be invoked before any relations
     * are eager loaded by the orm.
     *
     * @param callable(Model[]):void $fn
     *
     * @return QueryBaseInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     * @internal
     * @private
     */
    public function _internal_beforeRelations(callable $fn): QueryBaseInterface;

    /**
     * If {@see self::_internal_beforeRelations()} is set, that function
     * will be invoked.
     *
     * @param Model[] $instances
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     * @internal
     * @private
     */
    public function _internal_invokeBeforeRelations(array $instances): void;

}
