<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Cast;

use JsonException;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function json_validate;
use const JSON_HEX_AMP;
use const JSON_HEX_APOS;
use const JSON_HEX_QUOT;
use const JSON_HEX_TAG;
use const JSON_THROW_ON_ERROR;

/**
 * Class JsonCast
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Cast
 * @since 1.0.2
 */
final class JsonCast implements CastInterface
{

    /**
     * {@inheritdoc}
     * @throws JsonException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function decode(string|float|int|null $value): mixed
    {
        if (!is_string($value) || !json_validate($value)) {
            return null;
        }

        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * {@inheritdoc}
     * @throws JsonException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function encode(mixed $value): string|float|int|null
    {
        if (!is_array($value)) {
            return null;
        }

        return json_encode($value, JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_THROW_ON_ERROR);
    }

}
