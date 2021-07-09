<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use Raxos\Database\Connection\Connection;
use Raxos\Database\Error\ModelException;
use Raxos\Database\Orm\Defenition\FieldDefinition;
use Raxos\Database\Orm\Model;
use Raxos\Database\Orm\Relation\HasLinkedManyRelation;
use Raxos\Database\Orm\Relation\Relation;
use function is_array;
use function is_subclass_of;
use function sprintf;

/**
 * Class HasLinkedMany
 *
 * @template TModel of \Raxos\Database\Orm\Model
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.0
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class HasLinkedMany extends RelationAttribute
{

    /**
     * HasLinkedMany constructor.
     *
     * @param class-string<TModel> $referenceType
     * @param string|null $linkingTable
     * @param string|null $key
     * @param string|null $referenceKey
     * @param string|null $linkingKey
     * @param string|null $linkingReferenceKey
     * @param bool $eagerLoad
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        protected string $referenceType,
        protected ?string $linkingTable,
        protected ?string $key = null,
        protected ?string $referenceKey = null,
        protected ?string $linkingKey = null,
        protected ?string $linkingReferenceKey = null,
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
    public final function getKey(): ?string
    {
        return $this->key;
    }

    /**
     * Gets the linking column.
     *
     * @return string|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getLinkingKey(): ?string
    {
        return $this->linkingKey;
    }

    /**
     * Gets the linking rederence column.
     *
     * @return string|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getLinkingReferenceKey(): ?string
    {
        return $this->linkingReferenceKey;
    }

    /**
     * Gets the reference column.
     *
     * @return string|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getReferenceKey(): ?string
    {
        return $this->referenceKey;
    }

    /**
     * Gets the reference type.
     *
     * @return class-string<TModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getReferenceType(): string
    {
        return $this->referenceType;
    }

    /**
     * Gets the linking table.
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getLinkingTable(): string
    {
        return $this->linkingTable;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function create(Connection $connection, string $modelClass, FieldDefinition $field): Relation
    {
        /** @var Model $modelClass */

        $referenceType = $this->referenceType;

        if (!is_subclass_of($referenceType, Model::class)) {
            throw new ModelException(sprintf('Referenced model %s is not a model.', $referenceType), ModelException::ERR_NOT_A_MODEL);
        }

        $referenceType::initializeFromRelation();
        $primaryKey = $modelClass::getPrimaryKey();
        $referencePrimaryKey = $referenceType::getPrimaryKey();

        if (is_array($primaryKey)) {
            $primaryKey = $primaryKey[0];
        }

        if (is_array($referencePrimaryKey)) {
            $referencePrimaryKey = $referencePrimaryKey[0];
        }

        return new HasLinkedManyRelation(
            $connection,
            $referenceType,
            $this->eagerLoad,
            $field->name,
            $this->key ?? $primaryKey,
            $this->referenceKey ?? $referencePrimaryKey,
            $this->linkingKey ?? $modelClass::getTable() . '_' . $primaryKey,
            $this->linkingReferenceKey ?? $referenceType::getTable() . '_' . $primaryKey,
            $this->linkingTable
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
            $state['referenceType'],
            $state['linkingTable'],
            $state['key'],
            $state['referenceKey'],
            $state['linkingKey'],
            $state['linkingReferenceKey'],
            $state['eagerLoad']
        );
    }

}
