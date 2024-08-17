<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;

/**
 * Class Polymorphic
 *
 * Defines a polymorphic structure. This is used when multiple model types
 * share a single database table. By default, the `type` column is used to
 * determine which class should be used for the database record.
 *
 * <code>
 *     #[Polymorphic(map: [])]
 *     #[Table('shop_element')]
 *     abstract class ShopElement extends Model {
 *         // common fields
 *     }
 *
 *     class ShopElementButton extends ShopElement {
 *         // fields only for a button shop element
 *     }
 *
 *     class ShopElementProduct extends ShopElement {
 *         // fields only for a product shop element
 *     }
 * </code>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.17
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Polymorphic implements AttributeInterface
{

    /**
     * Polymorphic constructor.
     *
     * @param string $column
     * @param array $map
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public string $column = 'type',
        public array $map = []
    ) {}

}
