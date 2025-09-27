<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Error;

use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Database\Orm\Attribute\Table;
use Raxos\Error\Exception;

/**
 * Class MissingTableException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Error
 * @since 2.0.0
 */
final class MissingTableException extends Exception implements OrmExceptionInterface
{

    /**
     * MissingTableException constructor.
     *
     * @param string $modelClass
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function __construct(
        public readonly string $modelClass
    )
    {
        $tableAttribute = Table::class;

        parent::__construct(
            'db_orm_missing_table',
            "Model {$this->modelClass} is missing the {$tableAttribute} attribute."
        );
    }

}
