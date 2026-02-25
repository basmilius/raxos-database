<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Collection\ArrayList;
use Raxos\Contract\Collection\ArrayListInterface;
use Raxos\Contract\Database\Orm\{OrmExceptionInterface, RelationInterface, StructureInterface};
use Raxos\Contract\Database\Query\QueryInterface;
use Raxos\Database\Orm\{Error\ReferenceModelMissingException, Model, ModelArrayList};
use Raxos\Database\Orm\Attribute\BelongsToThrough;
use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Database\Orm\Structure\StructureGenerator;
use Raxos\Database\Query\Literal\ColumnLiteral;
use Raxos\Database\Query\Select;

/**
 * Class BelongsToThroughRelation
 *
 * @template TDeclaringModel of Model
 * @template TLinkingModel of Model
 * @template TReferenceModel of Model
 * @implements RelationInterface<TDeclaringModel, TReferenceModel>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Relation
 * @since 1.1.0
 */
final readonly class BelongsToThroughRelation implements RelationInterface
{

    public ColumnLiteral $declaringKey;
    public ColumnLiteral $declaringLinkingKey;
    public ColumnLiteral $referenceKey;
    public ColumnLiteral $referenceLinkingKey;

    public StructureInterface $linkingStructure;
    public StructureInterface $referenceStructure;

    /**
     * BelongsToThroughRelation constructor.
     *
     * @param BelongsToThrough $attribute
     * @param RelationDefinition $property
     * @param StructureInterface<TDeclaringModel> $declaringStructure
     *
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function __construct(
        public BelongsToThrough $attribute,
        public RelationDefinition $property,
        public StructureInterface $declaringStructure
    )
    {
        $referenceModel = $this->property->types[0] ?? throw new ReferenceModelMissingException($this->property, $this->declaringStructure);

        $this->linkingStructure = StructureGenerator::for($this->attribute->linkingModel);
        $this->referenceStructure = StructureGenerator::for($referenceModel);

        $linkingPrimaryKey = $this->linkingStructure->getRelationPrimaryKey();
        $referencePrimaryKey = $this->referenceStructure->getRelationPrimaryKey();

        $this->declaringKey = RelationHelper::composeKey(
            $this->declaringStructure->connection->grammar,
            $this->attribute->declaringKey,
            $this->attribute->declaringKeyTable,
            $linkingPrimaryKey->asForeignKeyFor($this->declaringStructure)
        );

        $this->declaringLinkingKey = RelationHelper::composeKey(
            $this->linkingStructure->connection->grammar,
            $this->attribute->declaringLinkingKey,
            $this->attribute->declaringLinkingKeyTable,
            $linkingPrimaryKey
        );

        $this->referenceLinkingKey = RelationHelper::composeKey(
            $this->linkingStructure->connection->grammar,
            $this->attribute->referenceLinkingKey,
            $this->attribute->referenceLinkingKeyTable,
            $referencePrimaryKey->asForeignKeyFor($this->linkingStructure)
        );

        $this->referenceKey = RelationHelper::composeKey(
            $this->referenceStructure->connection->grammar,
            $this->attribute->referenceKey,
            $this->attribute->referenceKeyTable,
            $referencePrimaryKey
        );
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function fetch(Model $instance): Model|ModelArrayList|null
    {
        $directRelation = RelationHelper::findRelationToModel($this->declaringStructure, $this->linkingStructure->class);

        if ($directRelation !== null && $instance->backbone->relationCache->hasValue($directRelation->name)) {
            $linkingInstance = $instance->backbone->relationCache->getValue($directRelation->name);

            if ($linkingInstance === null) {
                return null;
            }

            if ($linkingInstance instanceof Model) {
                $refRelation = RelationHelper::findRelationToModel($this->linkingStructure, $this->referenceStructure->class);

                if ($refRelation !== null && $linkingInstance->backbone->relationCache->hasValue($refRelation->name)) {
                    return $linkingInstance->backbone->relationCache->getValue($refRelation->name);
                }
            }
        }

        return $this
            ->query($instance)
            ->single();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function query(Model $instance): QueryInterface
    {
        return $this->referenceStructure->class::select()
            ->join($this->linkingStructure->table, fn(QueryInterface $query) => $query
                ->on($this->referenceLinkingKey, $this->referenceKey))
            ->where($this->declaringLinkingKey, $instance->{$this->declaringKey->column});
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function rawQuery(): QueryInterface
    {
        return $this->referenceStructure->class::select(prepared: false)
            ->join($this->linkingStructure->table, fn(QueryInterface $query) => $query
                ->on($this->referenceLinkingKey, $this->referenceKey))
            ->where($this->declaringLinkingKey, $this->declaringKey);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function eagerLoad(ArrayListInterface $instances): void
    {
        $directRelation = RelationHelper::findRelationToModel($this->declaringStructure, $this->linkingStructure->class);
        $refRelation = $directRelation !== null
            ? RelationHelper::findRelationToModel($this->linkingStructure, $this->referenceStructure->class)
            : null;

        $needsQuery = new ArrayList();

        foreach ($instances as $instance) {
            if ($instance->backbone->relationCache->hasValue($this->property->name)) {
                continue;
            }

            if ($directRelation !== null && $refRelation !== null
                && $instance->backbone->relationCache->hasValue($directRelation->name)) {
                $linkingInstance = $instance->backbone->relationCache->getValue($directRelation->name);

                if ($linkingInstance === null) {
                    $instance->backbone->relationCache->setValue($this->property->name, null);
                    continue;
                }

                if ($linkingInstance instanceof Model
                    && $linkingInstance->backbone->relationCache->hasValue($refRelation->name)) {
                    $instance->backbone->relationCache->setValue(
                        $this->property->name,
                        $linkingInstance->backbone->relationCache->getValue($refRelation->name)
                    );
                    continue;
                }
            }

            $needsQuery->append($instance);
        }

        $values = $needsQuery
            ->column($this->declaringKey->column)
            ->unique();

        if ($values->isEmpty()) {
            return;
        }

        $select = new Select()->add(
            $this->referenceStructure->class::col('*'),
            __local_linking_key: $this->declaringLinkingKey
        );

        $this->referenceStructure->class::select($select)
            ->join($this->linkingStructure->table, fn(QueryInterface $query) => $query
                ->on($this->referenceLinkingKey, $this->referenceKey))
            ->whereIn($this->declaringLinkingKey, $values)
            ->withQuery(RelationHelper::onBeforeRelations($needsQuery, $this->onBeforeRelations(...)))
            ->array();
    }

    /**
     * Apply the results to the instances' relation cache.
     *
     * @param ArrayListInterface<int, TReferenceModel> $results
     * @param ArrayListInterface<int, TDeclaringModel> $instances
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    private function onBeforeRelations(ArrayListInterface $results, ArrayListInterface $instances): void
    {
        $map = [];

        foreach ($results as $reference) {
            $map[$reference->backbone->data->getValue('__local_linking_key')] = $reference;
        }

        foreach ($instances as $instance) {
            $result = $map[$instance->{$this->declaringKey->column}] ?? null;

            if ($result === null) {
                continue;
            }

            $instance->backbone->relationCache->setValue(
                $this->property->name,
                $result
            );
        }
    }

}
