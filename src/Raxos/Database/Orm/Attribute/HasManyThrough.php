<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use Raxos\Database\Connection\Connection;
use Raxos\Database\Error\ModelException;
use Raxos\Database\Orm\Defenition\FieldDefinition;
use Raxos\Database\Orm\Model;
use Raxos\Database\Orm\Relation\HasManyThroughRelation;
use Raxos\Database\Orm\Relation\Relation;
use function is_array;
use function is_subclass_of;
use function sprintf;

/**
 * Class HasManyThrough
 *
 * @template TModel of \Raxos\Database\Orm\Model
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.0
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class HasManyThrough extends RelationAttribute
{

    /**
     * HasManyThrough constructor.
     *
     * @param string $type
     * @param string $throughType
     * @param string|null $column
     * @param string|null $referenceColumn
     * @param string|null $throughColumn
     * @param string|null $referenceThroughColumn
     * @param bool $eagerLoad
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        protected string $type,
        protected string $throughType,
        protected ?string $column = null,
        protected ?string $referenceColumn = null,
        protected ?string $throughColumn = null,
        protected ?string $referenceThroughColumn = null,
        bool $eagerLoad = false
    )
    {
        parent::__construct($eagerLoad);
    }

    /**
     * Gets the column.
     *
     * @return string|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getColumn(): ?string
    {
        return $this->column;
    }

    /**
     * Gets the reference column.
     *
     * @return string|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getReferenceColumn(): ?string
    {
        return $this->referenceColumn;
    }

    /**
     * Gets the through column.
     *
     * @return string|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getThroughColumn(): ?string
    {
        return $this->throughColumn;
    }

    /**
     * Gets the reference through column.
     *
     * @return string|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getReferenceThroughColumn(): ?string
    {
        return $this->referenceThroughColumn;
    }

    /**
     * Gets the type.
     *
     * @return class-string<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getType(): string
    {
        return $this->type;
    }

    /**
     * Gets the through type.
     *
     * @return class-string<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getThroughType(): string
    {
        return $this->throughType;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function create(Connection $connection, string $modelClass, FieldDefinition $field): Relation
    {
        /** @var Model $modelClass */

        $referenceModel = $this->type;
        $throughModel = $this->throughType;

        if (!is_subclass_of($referenceModel, Model::class)) {
            throw new ModelException(sprintf('Referenced model %s is not a model.', $referenceModel), ModelException::ERR_NOT_A_MODEL);
        }

        if (!is_subclass_of($throughModel, Model::class)) {
            throw new ModelException(sprintf('Referenced model %s is not a model.', $referenceModel), ModelException::ERR_NOT_A_MODEL);
        }

        $referenceModel::initializeFromRelation();
        $throughModel::initializeFromRelation();

        $primaryKey = $modelClass::getPrimaryKey();

        if (is_array($primaryKey)) {
            $primaryKey = $primaryKey[0];
        }

        return new HasManyThroughRelation(
            $connection,
            $referenceModel,
            $this->eagerLoad,
            $field->property,
            $this->column ?? $primaryKey,
            $this->referenceColumn ?? $modelClass::getTable() . '_' . $primaryKey,
            $throughModel,
            $this->throughColumn ?? $modelClass::getTable() . '_' . $primaryKey,
            $this->referenceThroughColumn ?? $throughModel::getTable() . '_' . $primaryKey
        );
    }

}
