<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use Raxos\Database\Connection\Connection;
use Raxos\Database\Error\ModelException;
use Raxos\Database\Orm\Defenition\FieldDefinition;
use Raxos\Database\Orm\Model;
use Raxos\Database\Orm\Relation\HasManyRelation;
use Raxos\Database\Orm\Relation\Relation;
use function is_array;
use function is_subclass_of;
use function sprintf;

/**
 * Class HasMany
 *
 * @template TModel of \Raxos\Database\Orm\Model
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.0
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class HasMany extends RelationAttribute
{

    /**
     * HasMany constructor.
     *
     * @param class-string<TModel> $type
     * @param string|null $column
     * @param string|null $referenceColumn
     * @param bool $eagerLoad
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        protected string $type,
        protected ?string $column = null,
        protected ?string $referenceColumn = null,
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
     * Gets the reference type.
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
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function create(Connection $connection, string $modelClass, FieldDefinition $field): Relation
    {
        /** @var Model $modelClass */

        $referenceModel = $this->type;

        if (!is_subclass_of($referenceModel, Model::class)) {
            throw new ModelException(sprintf('Referenced model %s is not a model.', $referenceModel), ModelException::ERR_NOT_A_MODEL);
        }

        $referenceModel::initializeFromRelation();
        $primaryKey = $modelClass::getPrimaryKey();

        if (is_array($primaryKey)) {
            $primaryKey = $primaryKey[0];
        }

        return new HasManyRelation(
            $connection,
            $referenceModel,
            $this->eagerLoad,
            $field->name,
            $this->column ?? $primaryKey,
            $this->referenceColumn ?? $modelClass::getTable() . '_' . $primaryKey,
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
            $state['column'],
            $state['referenceColumn'],
            $state['eagerLoad']
        );
    }

}
