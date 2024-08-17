<?php
declare(strict_types=1);

namespace Raxos\Database\Logger;

use Raxos\Database\Orm\Relation\RelationInterface;
use Raxos\Foundation\Util\Stopwatch;

/**
 * Class EagerLoadEvent
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Logger
 * @since 1.0.16
 */
final readonly class EagerLoadEvent extends Event
{

    /**
     * EagerLoadEvent constructor.
     *
     * @param RelationInterface $relation
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function __construct(
        public RelationInterface $relation
    )
    {
        parent::__construct(new Stopwatch());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function print(): string
    {
        $class = $this->relation::class;
        $flow = '';

        $declaringModel = $this->relation->declaringModel ?? null;
        $linkingModel = $this->relation->linkingModel ?? null;
        $referenceModel = $this->relation->referenceModel ?? null;

        if ($declaringModel !== null && $linkingModel !== null && $referenceModel !== null) {
            $flow = "{$declaringModel} ➜ {$linkingModel} ➜ {$referenceModel}";
        } elseif ($declaringModel !== null && $referenceModel !== null) {
            $flow = "{$declaringModel} ➜ {$referenceModel}";
        }

        return $this->printBase("{$class}({$flow})");
    }

}
