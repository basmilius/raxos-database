<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Error;

use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class ReadonlyModelException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Error
 * @since 3.1.0
 */
final class ReadonlyModelException extends Exception implements OrmExceptionInterface
{

    /**
     * ReadonlyModelException constructor.
     *
     * @param string $errorDescription
     *
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    private function __construct(string $errorDescription)
    {
        parent::__construct('db_orm_readonly_model', $errorDescription);
    }

    /**
     * Returns an exception for a write attempt on a read-only model.
     *
     * @param string $modelClass
     * @param string $key
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    public static function forWrite(string $modelClass, string $key): self
    {
        return new self("Cannot write to '{$key}' because '{$modelClass}' is a read-only model.");
    }

    /**
     * Returns an exception for a method call on a read-only model.
     *
     * @param string $modelClass
     * @param string $method
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    public static function forCall(string $modelClass, string $method): self
    {
        return new self("Cannot call '{$method}()' because '{$modelClass}' is a read-only model.");
    }

    /**
     * Returns an exception for an unset attempt on a read-only model.
     *
     * @param string $modelClass
     * @param string $key
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    public static function forUnset(string $modelClass, string $key): self
    {
        return new self("Cannot unset '{$key}' because '{$modelClass}' is a read-only model.");
    }

}
