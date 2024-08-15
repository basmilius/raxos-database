<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Backpack;

use Raxos\Foundation\Access\{ArrayAccessible, ObjectAccessible};
use Raxos\Foundation\PHP\MagicMethods\DebugInfoInterface;
use function array_key_exists;

/**
 * Class Backpack
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Backpack
 * @since 13-08-2024
 */
final class Backpack implements BackpackInterface, DebugInfoInterface
{

    use ArrayAccessible;
    use ObjectAccessible;

    /**
     * Backpack constructor.
     *
     * @param array $data
     *
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
     */
    public function __construct(
        private array $data = []
    ) {}

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
     */
    public function getValue(string $key): mixed
    {
        return $this->data[$key];
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
     */
    public function hasValue(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
     */
    public function setValue(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
     */
    public function unsetValue(string $key): void
    {
        unset($this->data[$key]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
     */
    public function __debugInfo(): ?array
    {
        return $this->data;
    }

}
