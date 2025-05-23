<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Database\Contract\QueryInterface;
use Raxos\Database\Orm\{Model, ModelArrayList};
use Raxos\Database\Orm\Attribute\HasOne;
use Raxos\Database\Orm\Contract\{RelationInterface, WritableRelationInterface};
use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Database\Orm\Error\{RelationException, StructureException};
use Raxos\Database\Orm\Structure\{Structure, StructureGenerator};
use Raxos\Database\Query\Literal\ColumnLiteral;
use Raxos\Foundation\Util\ArrayUtil;
use function assert;

/**
 * Class HasOneRelation
 *
 * @template TDeclaringModel of Model
 * @template TReferenceModel of Model
 * @implements RelationInterface<TDeclaringModel, TReferenceModel>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Relation
 * @since 1.0.17
 */
final readonly class HasOneRelation implements RelationInterface, WritableRelationInterface
{

    public ColumnLiteral $declaringKey;
    public ColumnLiteral $referenceKey;

    public Structure $referenceStructure;

    /**
     * HasOneRelation constructor.
     *
     * @param HasOne $attribute
     * @param RelationDefinition $property
     * @param Structure<TDeclaringModel|Model> $declaringStructure
     *
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public HasOne $attribute,
        public RelationDefinition $property,
        public Structure $declaringStructure
    )
    {
        $referenceModel = $this->property->types[0] ?? throw RelationException::referenceModelMissing($this->property, $this->declaringStructure);
        $this->referenceStructure = StructureGenerator::for($referenceModel);

        $declaringPrimaryKey = $this->declaringStructure->getRelationPrimaryKey();

        $this->referenceKey = RelationHelper::composeKey(
            $this->referenceStructure->connection->grammar,
            $this->attribute->referenceKey,
            $this->attribute->referenceKeyTable,
            $declaringPrimaryKey->asForeignKeyFor($this->referenceStructure),
        );

        $this->declaringKey = RelationHelper::composeKey(
            $this->declaringStructure->connection->grammar,
            $this->attribute->declaringKey,
            $this->attribute->declaringKeyTable,
            $declaringPrimaryKey
        );
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function fetch(Model $instance): Model|ModelArrayList|null
    {
        $declaringValue = RelationHelper::declaringKeyValue($instance, $this->declaringKey);

        if ($declaringValue === null) {
            return null;
        }

        $cached = RelationHelper::findCached(
            $declaringValue,
            $this->referenceStructure,
            $this->referenceKey
        );

        if ($cached !== null) {
            return $cached;
        }

        return $this
            ->query($instance)
            ->single();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function query(Model $instance): QueryInterface
    {
        return $this->referenceStructure->class::where($this->referenceKey, $instance->{$this->declaringKey->column})
            ->conditional($this->attribute->orderBy !== null, fn(QueryInterface $query) => $query
                ->orderBy($this->attribute->orderBy));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function rawQuery(): QueryInterface
    {
        return $this->referenceStructure->class::select(prepared: false)
            ->where($this->referenceKey, $this->declaringKey)
            ->conditional($this->attribute->orderBy !== null, fn(QueryInterface $query) => $query
                ->orderBy($this->attribute->orderBy));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function eagerLoad(ModelArrayList $instances): void
    {
        $cache = $this->referenceStructure->connection->cache;

        $values = $instances
            ->column($this->declaringKey->column)
            ->unique()
            ->filter(fn(string|int $key) => !$cache->has($this->referenceStructure->class, $key));

        if ($values->isEmpty()) {
            return;
        }

        $this->referenceStructure->class::select()
            ->whereIn($this->referenceKey, $values->toArray())
            ->conditional($this->attribute->orderBy !== null, fn(QueryInterface $query) => $query
                ->orderBy($this->attribute->orderBy))
            ->withQuery(RelationHelper::onBeforeRelations($instances, $this->onBeforeRelations(...)))
            ->array();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function write(Model $instance, RelationDefinition $property, Model|ModelArrayList|null $newValue): void
    {
        assert($newValue === null || $newValue instanceof $this->referenceStructure->class);

        // note(Bas): remove the relation between the previous value and the instance.
        if (!$instance->backbone->isNew) {
            $oldValue = $instance->{$this->property->name};

            if ($oldValue instanceof Model) {
                $instance->backbone->addSaveTask(function () use ($oldValue): void {
                    $oldValue->{$this->referenceKey->column} = null;
                    $oldValue->save();
                });
            }
        }

        // note(Bas): create a relation between the new value and the instance.
        if ($newValue instanceof Model) {
            $instance->backbone->addSaveTask(function () use ($instance, $newValue): void {
                $newValue->{$this->referenceKey->column} = $instance->{$this->declaringKey->column};
                $newValue->save();
            });
        }
    }

    /**
     * Apply the results to the instances' relation cache.
     *
     * @param Model[] $results
     * @param ModelArrayList<int, Model> $instances
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    private function onBeforeRelations(array $results, ModelArrayList $instances): void
    {
        foreach ($instances as $instance) {
            $result = ArrayUtil::first($results, fn(Model $reference) => $reference->{$this->referenceKey->column} === $instance->{$this->declaringKey->column});

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
