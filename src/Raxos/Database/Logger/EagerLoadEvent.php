<?php
declare(strict_types=1);

namespace Raxos\Database\Logger;

use Raxos\Database\Orm\Contract\RelationInterface;
use Raxos\Foundation\Util\{Stopwatch, StringUtil};

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
     * @param int $events
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function __construct(
        public RelationInterface $relation,
        public int $events
    )
    {
        parent::__construct(new Stopwatch());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function print(bool $backtrace): string
    {
        $class = $this->relation::class;
        $flow = '';

        $declaringModel = $this->shortClassName($this->relation->declaringStructure?->class ?? null);
        $linkingModel = $this->shortClassName($this->relation->linkingStructure?->class ?? null);
        $referenceModel = $this->shortClassName($this->relation->referenceStructure?->class ?? null);

        if ($declaringModel !== null && $linkingModel !== null && $referenceModel !== null) {
            $flow = "{$declaringModel} ➜ {$linkingModel} ➜ {$referenceModel}";
        } elseif ($declaringModel !== null && $referenceModel !== null) {
            $flow = "{$declaringModel} ➜ {$referenceModel}";
        }

        $shortClass = StringUtil::shortClassName($class);
        $class = "<abbr title='{$class}'>{$shortClass}</abbr>";

        return $this->printBase("{$class}({$flow})", $backtrace, "Triggered {$this->events} events");
    }

    /**
     * Returns the short class name.
     *
     * @param string|null $className
     *
     * @return string|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    private function shortClassName(?string $className): ?string
    {
        if ($className === null) {
            return null;
        }

        return StringUtil::shortClassName($className);
    }

}
