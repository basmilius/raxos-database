<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Raxos\Database\Orm\Definition\ColumnDefinition;
use Raxos\Database\Orm\Model;
use Raxos\Database\Orm\Relation\RelationInterface;

/**
 * Interface CustomRelationInterface
 *
 * @template TDeclaringModel of Model
 * @template TReferenceModel of Model
 * @extends RelationInterface<TDeclaringModel, TReferenceModel>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.16
 */
interface CustomRelationInterface extends AttributeInterface, RelationAttributeInterface
{

    /**
     * Creates the relation instance.
     *
     * @param CustomRelationInterface $attribute
     * @param ColumnDefinition $column
     * @param class-string<TDeclaringModel> $declaringModel
     *
     * @return RelationInterface<TDeclaringModel, TReferenceModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function createRelationInstance(CustomRelationInterface $attribute, ColumnDefinition $column, string $declaringModel): RelationInterface;

}
