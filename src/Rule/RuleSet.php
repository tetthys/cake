<?php

declare(strict_types=1);

namespace Tetthys\Cake\Rule;

/**
 * RuleSet is an ordered list of rules (OR-combination).
 * Deny-by-default: if none match, decision is DENY.
 */
final class RuleSet
{
    /** @param Rule[] $rules */
    public function __construct(public readonly array $rules = []) {}

    public function add(Rule $rule): self
    {
        return new self([...$this->rules, $rule]);
    }
}
