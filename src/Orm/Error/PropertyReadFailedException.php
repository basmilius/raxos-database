<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Error;

use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Error\Exception;
use Throwable;

/**
 * Class PropertyReadFailedException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Error
 * @since 2.0.0
 */
final class PropertyReadFailedException extends Exception implements OrmExceptionInterface
{

    /**
     * PropertyReadFailedException constructor.
     *
     * @param string $modelClass
     * @param string $propertyName
     * @param Throwable|null $previous
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly string $propertyName,
        ?Throwable $previous = null
    )
    {
        parent::__construct(
            'db_orm_property_read_failed',
            "Cannot read from property {$this->modelClass->{$this->propertyName}} because of an error.",
            previous: $previous,
        );
    }

}
