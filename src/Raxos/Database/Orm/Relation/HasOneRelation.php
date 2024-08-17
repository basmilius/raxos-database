<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Database\Orm\{Model, ModelArrayList};
use Raxos\Database\Orm\Attribute\HasOne;
use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Database\Orm\Error\{RelationException, StructureException};
use Raxos\Database\Orm\Structure\{Structure, StructureHelper};
use Raxos\Database\Query\QueryInterface;
use Raxos\Database\Query\Struct\ColumnLiteral;
use function assert;
use function is_numeric;

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
        $this->referenceStructure = Structure::of($referenceModel);

        $declaringPrimaryKey = $this->declaringStructure->getRelationPrimaryKey();

        $this->referenceKey = StructureHelper::composeRelationKey(
            $this->referenceStructure->connection->dialect,
            $this->attribute->referenceKey,
            $this->attribute->referenceKeyTable,
            $declaringPrimaryKey->asForeignKeyFor($this->referenceStructure),
        );

        $this->declaringKey = StructureHelper::composeRelationKey(
            $this->declaringStructure->connection->dialect,
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
        $foreignKey = $instance->{$this->declaringKey->column};

        if ($foreignKey === null || (is_numeric($foreignKey) && (int)$foreignKey === 0)) {
            return null;
        }

        $cache = $this->referenceStructure->connection->cache;

        if (($cached = $cache->find($this->referenceStructure->class, fn(Model $model) => $model->{$this->referenceKey->column} === $foreignKey)) !== null) {
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
        return $this->referenceStructure->class::where($this->referenceKey, $instance->{$this->declaringKey->column});
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function rawQuery(): QueryInterface
    {
        return $this->referenceStructure->class::select(prepared: false)
            ->where($this->referenceKey, $this->declaringKey);
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
        $oldValue = $instance->{$this->property->name};

        if ($oldValue instanceof Model) {
            $oldValue->{$this->referenceKey->column} = null;
            $instance->backbone->addSaveTask(static fn() => $oldValue->save());
        }

        // note(Bas): create a relation between the new value and the instance.
        if ($newValue instanceof Model) {
            $newValue->{$this->referenceKey->column} = $instance->{$this->declaringKey->column};
            $instance->backbone->addSaveTask(static fn() => $newValue->save());
        }
    }

}
