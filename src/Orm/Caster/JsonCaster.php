<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Caster;

use JsonException;
use Raxos\Contract\Database\Orm\CasterInterface;
use Raxos\Database\Orm\Model;
use function error_log;
use function is_string;
use function json_decode;
use function json_encode;
use function json_validate;
use function sprintf;
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
    public function decode(float|int|string|null $value, Model $instance): mixed
    {
        if (!is_string($value)) {
            return null;
        }

        if (!json_validate($value)) {
            error_log(sprintf(
                '[raxos/database] JsonCaster::decode() received invalid JSON for %s; returning null.',
                $instance::class
            ));

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
