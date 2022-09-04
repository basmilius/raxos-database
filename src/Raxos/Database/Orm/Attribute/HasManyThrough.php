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
        protected readonly string $type,
        protected readonly string $throughType,
        protected readonly ?string $column = null,
        protected readonly ?string $referenceColumn = null,
        protected readonly ?string $throughColumn = null,
        protected readonly ?string $referenceThroughColumn = null,
        bool $eagerLoad = false
    )
    {
        parent::__construct($eagerLoad);
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
            $field->name,
            $this->column ?? $primaryKey,
            $this->referenceColumn ?? $modelClass::table() . '_' . $primaryKey,
            $throughModel,
            $this->throughColumn ?? $modelClass::table() . '_' . $primaryKey,
            $this->referenceThroughColumn ?? $throughModel::table() . '_' . $primaryKey
        );
    }

    /**
     * Restores the state of the class from exported data.
     *
     * @param array $state
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function __set_state(array $state): self
    {
        return new self(
            $state['type'],
            $state['throughType'],
            $state['column'],
            $state['referenceColumn'],
            $state['throughColumn'],
            $state['referenceThroughColumn'],
            $state['eagerLoad']
        );
    }

}
