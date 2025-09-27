<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Error;

use Raxos\Contract\Database\Orm\CasterInterface;
use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class InvalidCasterException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Error
 * @since 2.0.0
 */
final class InvalidCasterException extends Exception implements OrmExceptionInterface
{

    /**
     * InvalidCasterException constructor.
     *
     * @param string $modelClass
     * @param string $propertyName
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly string $propertyName
    )
    {
        $casterInterface = CasterInterface::class;

        parent::__construct(
            'db_orm_invalid_caster',
            "Property {$this->modelClass->{$this->propertyName}} is not a valid caster. Casters should implement {$casterInterface}.",
        );
    }

}
