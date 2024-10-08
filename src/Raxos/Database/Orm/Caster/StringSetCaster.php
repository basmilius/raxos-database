<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Caster;

use Raxos\Database\Orm\Contract\CasterInterface;
use Raxos\Database\Orm\Model;
use function array_filter;
use function explode;
use function implode;
use function is_array;
use function is_string;

/**
 * Class StringSetCaster
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Caster
 * @since 1.0.17
 */
final readonly class StringSetCaster implements CasterInterface
{

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function decode(float|int|string|null $value, Model $instance): array
    {
        if (!is_string($value)) {
            return [];
        }

        return array_filter(explode(',', $value));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function encode(mixed $value, Model $instance): string|float|int|null
    {
        if (!is_array($value)) {
            return null;
        }

        return implode(',', $value);
    }

}
