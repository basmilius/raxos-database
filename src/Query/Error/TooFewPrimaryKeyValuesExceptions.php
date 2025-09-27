<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Error;

use Raxos\Contract\Database\Query\QueryExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class TooFewPrimaryKeyValuesExceptions
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Error
 * @since 2.0.0
 */
final class TooFewPrimaryKeyValuesExceptions extends Exception implements QueryExceptionInterface
{

    /**
     * TooFewPrimaryKeyValuesExceptions constructor.
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
        parent::__construct(
            'db_query_too_few_primary_key_values',
            "Too few primary key values specified for model {$this->modelClass}."
        );
    }

}
