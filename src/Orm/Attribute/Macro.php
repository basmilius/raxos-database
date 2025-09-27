<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use Raxos\Contract\Database\Orm\AttributeInterface;

/**
 * Class Macro
 *
 * Defines a macro. A macro is a calculated value based on other values
 * of the containing model. Marcos are calculated when accessed and are
 * cached by default to improve performance.
 *
 * Macros are hidden by default and should be marked with {@see Visible}
 * to show them in responses.
 *
 * <code>
 * class User extends Model {
 *     #[Column]
 *     public string $firstName;
 *
 *     #[Column]
 *     public string $lastName;
 *
 *     #[Macro([UserMacro::class, 'getFullName'])]
 *     public string $fullName;
 * }
 * </code>
 *
 * <code>
 * class UserMacro {
 *     public static function getFullName(User $user): string {
 *         return "{$user->firstName} {$user->lastName}";
 *     }
 * }
 * </code>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.17
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Macro implements AttributeInterface
{

    /**
     * Macro constructor.
     *
     * @param (callable&string)|(callable&array) $callback
     * @param bool $isCached
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public string|array $callback,
        public bool $isCached = true
    ) {}

}
