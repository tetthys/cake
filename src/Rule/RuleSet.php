<?php

declare(strict_types=1);

namespace Tetthys\Cake\Rule;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * Ordered, immutable collection of Rule objects.
 *
 * Combination semantics (AND / OR) is controlled by $mode.
 * Deny-by-default: if evaluation yields no PERMIT, the Engine will deny.
 *
 * @implements IteratorAggregate<int, Rule>
 */
final readonly class RuleSet implements IteratorAggregate, Countable
{
    /** @var list<Rule> */
    private array $items;

    public function __construct(
        iterable $rules = [],
        public RuleSetMode $mode = RuleSetMode::AnyMayMatch, // default OR
    ) {
        $tmp = [];
        foreach ($rules as $rule) {
            if (!$rule instanceof Rule) {
                // Fail fast if non-Rule is passed in
                throw new \TypeError("RuleSet expects iterable<Rule>.");
            }
            $tmp[] = $rule;
        }

        $this->items = $tmp;
    }

    /**
     * Named constructor for AND-combined rules.
     *
     * @param iterable<Rule> $rules
     */
    public static function allOf(iterable $rules = []): self
    {
        return new self($rules, RuleSetMode::AllMustMatch);
    }

    /**
     * Named constructor for OR-combined rules.
     *
     * @param iterable<Rule> $rules
     */
    public static function anyOf(iterable $rules = []): self
    {
        return new self($rules, RuleSetMode::AnyMayMatch);
    }

    /**
     * Return a new RuleSet with the given rule appended (immutable).
     */
    public function add(Rule $rule): self
    {
        return new self([...$this->items, $rule], $this->mode);
    }

    /**
     * Return a new RuleSet with the same rules but a different mode.
     */
    public function withMode(RuleSetMode $mode): self
    {
        return new self($this->items, $mode);
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
