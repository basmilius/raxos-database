<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Contract;

use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Database\Orm\Model;

/**
 * Interface CustomRelationAttributeInterface
 *
 * <code>
 * #[Attribute(Attribute::TARGET_PROPERTY)]
 * class Friends implements CustomRelationAttributeInterface
 * {
 *     public function createRelationInstance(RelationDefinition $property, StructureInterface $declaringStructure): RelationInterface
 *     {
 *         return new FriendsRelation($this, $property, $declaringStructure);
 *     }
 * }
 * </code>
 *
 * @template TDeclaringModel of Model
 * @template TReferenceModel of Model
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Contract
 * @since 1.1.0
 */
interface CustomRelationAttributeInterface
{

    /**
     * Creates the corresponding relation instance for the attribute.
     *
     * @param RelationDefinition $property
     * @param StructureInterface<TDeclaringModel> $declaringStructure
     *
     * @return RelationInterface<TDeclaringModel, TReferenceModel>
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function createRelationInstance(RelationDefinition $property, StructureInterface $declaringStructure): RelationInterface;

}
