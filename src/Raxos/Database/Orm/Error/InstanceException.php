<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Error;

use Raxos\Database\Error\DatabaseException;
use Raxos\Foundation\Error\ExceptionId;
use function json_encode;
use function sprintf;

/**
 * Class InstanceException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Error
 * @since 1.0.17
 */
final class InstanceException extends OrmException
{

    /**
     * Returns an immutable exception.
     *
     * @param string $modelClass
     * @param string $propertyName
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function immutable(string $modelClass, string $propertyName): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_orm_immutable',
            sprintf('Cannot write to property "%s" of model "%s" because it is immutable.', $propertyName, $modelClass),
        );
    }

    /**
     * Returns an immutable exception for use with macros.
     *
     * @param string $modelClass
     * @param string $propertyName
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function immutableMacro(string $modelClass, string $propertyName): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_orm_immutable',
            sprintf('Cannot write to property "%s" of model "%s" because it is a macro.', $propertyName, $modelClass),
        );
    }

    /**
     * Returns an immutable exception for use with primary keys.
     *
     * @param string $modelClass
     * @param string $propertyName
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function immutablePrimaryKey(string $modelClass, string $propertyName): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_orm_immutable',
            sprintf('Cannot write to property "%s" of model "%s" because it is (part of) the primary key.', $propertyName, $modelClass),
        );
    }

    /**
     * Returns an immutable exception for use with relations.
     *
     * @param string $modelClass
     * @param string $propertyName
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function immutableRelation(string $modelClass, string $propertyName): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_orm_immutable',
            sprintf('Cannot write to property "%s" of model "%s" because it is a non-writable relation.', $propertyName, $modelClass),
        );
    }

    /**
     * Returns a missing function exception.
     *
     * @param string $modelClass
     * @param string $functionName
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function missingFunction(string $modelClass, string $functionName): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_orm_missing_function',
            sprintf('Invocation of function "%s" failed on model "%s". An implementation was missing.', $functionName, $modelClass),
        );
    }

    /**
     * Returns a not found exception.
     *
     * @param string $modelClass
     * @param array|string|int $primaryKey
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function notFound(string $modelClass, array|string|int $primaryKey): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_orm_not_found',
            sprintf('An instance of model "%s" with primary key "%s" could not be found.', $modelClass, json_encode($primaryKey)),
        );
    }

    /**
     * Returns a read failed exception.
     *
     * @param string $modelClass
     * @param string $propertyName
     * @param DatabaseException $err
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function readFailed(string $modelClass, string $propertyName, DatabaseException $err): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_orm_read_failed',
            sprintf('Could not read property "%s" of model "%s".', $propertyName, $modelClass),
            $err
        );
    }

    /**
     * Returns a write-failed exception.
     *
     * @param string $modelClass
     * @param string $propertyName
     * @param DatabaseException $err
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function writeFailed(string $modelClass, string $propertyName, DatabaseException $err): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_orm_write_failed',
            sprintf('Could not write property "%s" of model "%s".', $propertyName, $modelClass),
            $err
        );
    }

}
