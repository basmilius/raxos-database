<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Database\Orm\Model;
use Raxos\Database\Orm\Relation\RelationInterface;
use Raxos\Database\Orm\Structure\Structure;

/**
 * Interface CustomRelationAttributeInterface
 *
 * <code>
 *     #[Attribute(Attribute::TARGET_PROPERTY)]
 *     class Friends implement CustomRelationAttributeInterface
 *     {
 *         public function createRelationInstance(RelationDefinition $property, Structure $declaringStructure): RelationInterface
 *         {
 *             return new FriendsRelation($this, $property, $declaringStructure);
 *         }
 *     }
 * </code>
 *
 * @template TDeclaringModel of Model
 * @template TReferenceModel of Model
 * @extends RelationInterface<TDeclaringModel, TReferenceModel>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.17
 */
interface CustomRelationAttributeInterface extends RelationAttributeInterface
{

    /**
     * Creates the relation instance.
     *
     * @param RelationDefinition $property
     * @param Structure<TDeclaringModel|Model> $declaringStructure
     *
     * @return RelationInterface<TDeclaringModel, TReferenceModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function createRelationInstance(RelationDefinition $property, Structure $declaringStructure): RelationInterface;

}
