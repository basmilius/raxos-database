<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Error;

use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Contract\Database\Query\QueryExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class StructureErrorException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Error
 * @since 2.0.0
 */
final class StructureErrorException extends Exception implements QueryExceptionInterface
{

    /**
     * StructureErrorException constructor.
     *
     * @param string $modelClass
     * @param OrmExceptionInterface $err
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        private readonly string $modelClass,
        private readonly OrmExceptionInterface $err
    )
    {
        parent::__construct(
            'db_query_structure_error',
            "Structure generation process failed for model {$this->modelClass}.",
            previous: $this->err
        );
    }

}
