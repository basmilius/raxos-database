<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Caster;

use DateTimeImmutable;
use Exception;
use Raxos\Contract\Database\Orm\CasterInterface;
use Raxos\Database\Orm\Model;

/**
 * Class DateTimeCaster
 *
 * Casts a database datetime string to a {@see DateTimeImmutable} instance
 * and back. The value is stored in the database as a `Y-m-d H:i:s` string.
 *
 * <code>
 * class Post extends Model {
 *     #[Column]
 *     #[Caster(DateTimeCaster::class)]
 *     public ?DateTimeImmutable $publishedAt;
 * }
 * </code>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Caster
 * @since 2.0.0
 */
final readonly class DateTimeCaster implements CasterInterface
{

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function decode(float|int|string|null $value, Model $instance): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        try {
            return new DateTimeImmutable((string)$value);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function encode(mixed $value, Model $instance): string|float|int|null
    {
        if ($value === null) {
            return null;
        }

        if (!($value instanceof DateTimeImmutable)) {
            return null;
        }

        return $value->format('Y-m-d H:i:s');
    }

}
