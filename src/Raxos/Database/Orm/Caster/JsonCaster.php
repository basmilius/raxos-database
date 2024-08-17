<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Caster;

use JsonException;
use Raxos\Database\Orm\Model;
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
 * Class JsonCaster
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Caster
 * @since 1.0.17
 */
final readonly class JsonCaster implements CasterInterface
{

    /**
     * {@inheritdoc}
     * @throws JsonException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function decode(float|int|string|null $value, Model $instance): ?array
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
     * @since 1.0.17
     */
    public function encode(mixed $value, Model $instance): string|float|int|null
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value, JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_THROW_ON_ERROR);
    }

}
