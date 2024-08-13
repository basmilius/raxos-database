<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Orm\{InternalHelper, InternalStructure, Model};
use Raxos\Database\Orm\Attribute\HasOne;
use Raxos\Database\Orm\Definition\ColumnDefinition;
use Raxos\Database\Query\QueryInterface;
use Raxos\Database\Query\Struct\ColumnLiteral;
use Raxos\Foundation\Collection\ArrayList;
use function assert;
use function is_numeric;
use function Raxos\Database\Query\in;

/**
 * Class HasOneRelation
 *
 * @template TDeclaringModel of Model
 * @template TReferenceModel of Model
 * @implements RelationInterface<TDeclaringModel, TReferenceModel>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Relation
 * @since 1.0.16
 */
final readonly class HasOneRelation implements RelationInterface, WritableRelationInterface
{

    /** @var class-string<TReferenceModel>|class-string<Model> */
    public string $referenceModel;

    public ColumnLiteral $referenceKey;
    public ColumnLiteral $declaringKey;

    /**
     * HasOneRelation constructor.
     *
     * @param HasOne $attribute
     * @param ColumnDefinition $column
     * @param class-string<TDeclaringModel>|class-string<Model> $declaringModel
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function __construct(
        public HasOne $attribute,
        public ColumnDefinition $column,
        public string $declaringModel
    )
    {
        $this->referenceModel = $this->column->types[0] ?? 0;
        InternalStructure::initialize($this->referenceModel);

        $dialect = $this->referenceModel::dialect();
        $declaringPrimaryKey = InternalHelper::getRelationPrimaryKey($this->declaringModel);

        $this->referenceKey = InternalHelper::composeRelationKey(
            $dialect,
            $this->attribute->referenceKey,
            $this->attribute->referenceKeyTable,
            $declaringPrimaryKey->asForeignKeyFor($this->referenceModel)
        );

        $this->declaringKey = InternalHelper::composeRelationKey(
            $dialect,
            $this->attribute->declaringKey,
            $this->attribute->declaringKeyTable,
            $declaringPrimaryKey
        );
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function fetch(Model $instance): ?Model
    {
        $primaryKey = $instance->{$this->declaringKey->column};

        if ($primaryKey === null || (is_numeric($primaryKey) && (int)$primaryKey === 0)) {
            return null;
        }

        $cache = $instance::cache();

        if (($cached = $cache->find($this->referenceModel, fn(Model $model) => $model->{$this->referenceKey->column} === $instance->{$this->declaringKey->column})) !== null) {
            return $cached;
        }

        return $this
            ->query($instance)
            ->single();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function query(Model $instance): QueryInterface
    {
        return $this->referenceModel::where($this->referenceKey, $instance->{$this->declaringKey->column});
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function rawQuery(): QueryInterface
    {
        return $this->referenceModel::select(isPrepared: false)
            ->where($this->referenceKey, $this->declaringKey);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function eagerLoad(ArrayList $instances): void
    {
        $cache = $this->referenceModel::cache();

        $values = $instances
            ->column($this->declaringKey->column)
            ->unique()
            ->filter(fn(string|int $key) => !$cache->has($this->referenceModel, $key));

        if ($values->isEmpty()) {
            return;
        }

        $this->referenceModel::select()
            ->where($this->referenceKey, in($values->toArray()))
            ->arrayList();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function write(Model $instance, ColumnDefinition $column, mixed $newValue): void
    {
        assert($newValue === null || $newValue instanceof $this->referenceModel);

        // note(Bas): remove the relation between the previous value and the instance.
        $oldValue = $instance->{$this->column->name};

        if ($oldValue instanceof Model) {
            $oldValue->{$this->referenceKey->column} = null;
            $instance->__saveTasks[] = static fn() => $oldValue->save();
        }

        // note(Bas): create a relation between the new value and the instance.
        if ($newValue instanceof Model) {
            $newValue->{$this->referenceKey->column} = $instance->{$this->declaringKey->column};
            $instance->__saveTasks[] = static fn() => $newValue->save();
        }
    }

}
