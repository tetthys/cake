<?php

declare(strict_types=1);

namespace Tetthys\Cake\Rule;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * Ordered, immutable collection of Rule objects combined by OR.
 * Deny-by-default: if none match, the Engine will deny.
 *
 * @implements IteratorAggregate<int, Rule>
 */
final readonly class RuleSet implements IteratorAggregate, Countable
{
    /** @var list<Rule> */
    private array $items;

    /**
     * @param iterable<Rule> $rules
     */
    public function __construct(iterable $rules = [])
    {
        $tmp = [];
        foreach ($rules as $rule) {
            if (!$rule instanceof Rule) {
                throw new \TypeError("RuleSet expects iterable<Rule>.");
            }
            $tmp[] = $rule;
        }
        $this->items = $tmp;
    }

    /** Return a new RuleSet with the given rule appended (immutable). */
    public function add(Rule $rule): self
    {
        return new self([...$this->items, $rule]);
    }

    /** @return ArrayIterator<int, Rule> */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    public function count(): int
    {
        return \count($this->items);
    }
}
