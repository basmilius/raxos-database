<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Error;

use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Database\Orm\Model;
use Raxos\Error\Exception;
use function json_encode;

/**
 * Class NotFoundException
 *
 * @template TModel of Model
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Error
 * @since 2.0.0
 */
final class NotFoundException extends Exception implements OrmExceptionInterface
{

    /**
     * NotFoundException constructor.
     *
     * @param class-string<TModel> $modelClass
     * @param array|string|int $primaryKey
     *
     * @author Bas Milius <bas@mili.us>
     * @package Raxos\Database\Orm\Error
     * @since 2.0.0
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly array|string|int $primaryKey
    )
    {
        $primaryKey = json_encode($this->primaryKey);

        parent::__construct(
            'db_orm_not_found',
            "An instance of model {$this->modelClass} with primary key {$primaryKey} cannot be found."
        );
    }

}
