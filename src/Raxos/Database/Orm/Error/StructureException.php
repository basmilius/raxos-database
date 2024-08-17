<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Error;

use Raxos\Database\Error\ConnectionException;
use Raxos\Database\Orm\Attribute\Table;
use Raxos\Database\Orm\Caster\CasterInterface;
use Raxos\Foundation\Error\ExceptionId;
use ReflectionException;
use function sprintf;

/**
 * Class StructureException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Error
 * @since 1.0.17
 */
final class StructureException extends OrmException
{

    /**
     * Returns a connection failed exception.
     *
     * @param string $modelClass
     * @param ConnectionException $err
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function connectionFailed(string $modelClass, ConnectionException $err): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_orm_connection_failed',
            sprintf('Could not get the database connection for model "%s".', $modelClass),
            $err
        );
    }

    /**
     * Returns an invalid caster exception.
     *
     * @param string $modelClass
     * @param string $propertyName
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function invalidCaster(string $modelClass, string $propertyName): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_orm_invalid_caster',
            sprintf('Property "%s" of model "%s" has an invalid caster. Casters should implement "%s".', $propertyName, $modelClass, CasterInterface::class)
        );
    }

    /**
     * Returns an invalid column exception.
     *
     * @param string $modelClass
     * @param string $propertyName
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function invalidColumn(string $modelClass, string $propertyName): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_orm_invalid_column',
            sprintf('Property "%s" of model "%s" is not a column.', $propertyName, $modelClass)
        );
    }

    /**
     * Returns an invalid macro exception.
     *
     * @param string $modelClass
     * @param string $propertyName
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function invalidMacro(string $modelClass, string $propertyName): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_orm_invalid_macro',
            sprintf('Property "%s" of model "%s" is missing its macro callback or the macro callback is not accessible.', $propertyName, $modelClass)
        );
    }

    /**
     * Returns an invalid relation exception.
     *
     * @param string $modelClass
     * @param string $propertyName
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function invalidRelation(string $modelClass, string $propertyName): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_orm_invalid_relation',
            sprintf('Property "%s" of model "%s" has an invalid relation.', $propertyName, $modelClass)
        );
    }

    /**
     * Returns a missing property exception.
     *
     * @param string $modelClass
     * @param string $propertyName
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function missingProperty(string $modelClass, string $propertyName): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_orm_missing_property',
            sprintf('Model "%s" does not have a property called "%s".', $modelClass, $propertyName)
        );
    }

    /**
     * Returns a missing relation implementation exception.
     *
     * @param string $modelClass
     * @param string $propertyName
     * @param string $relationType
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function missingRelationImplementation(string $modelClass, string $propertyName, string $relationType): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_orm_missing_relation_implementation',
            sprintf('Implementation for relation "%s" is missing for property "%s" on model "%s".', $relationType, $propertyName, $modelClass)
        );
    }

    /**
     * Returns a missing table exception.
     *
     * @param string $modelClass
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function missingTable(string $modelClass): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_orm_missing_table',
            sprintf('Model "%s" is missing the %s attribute.', $modelClass, Table::class)
        );
    }

    /**
     * Returns a not a model exception.
     *
     * @param string $className
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function notAModel(string $className): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_orm_not_a_model',
            sprintf('Cannot create a model structure for class "%s", the class is not a model.', $className)
        );
    }

    /**
     * Returns a polymorphic column missing exception.
     *
     * @param string $modelClass
     * @param string $columnName
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function polymorphicColumnMissing(string $modelClass, string $columnName): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_orm_polymorphic_column_missing',
            sprintf('Cannot create a new instance of polymorphic model "%s". The discriminator column "%s" is missing in the result.', $modelClass, $columnName)
        );
    }

    /**
     * Returns a reflection error exception.
     *
     * @param string $modelClass
     * @param ReflectionException $err
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function reflectionError(string $modelClass, ReflectionException $err): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'db_orm_reflection_failed',
            sprintf('Cannot create a structure for model "%s" due to a reflection error.', $modelClass),
            $err
        );
    }

}
