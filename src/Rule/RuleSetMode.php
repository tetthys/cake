<?php

declare(strict_types=1);

namespace Tetthys\Cake\Rule;

/**
 * Combination mode for RuleSet evaluation.
 */
enum RuleSetMode: string
{
    case AllMustMatch = 'AND'; // logical AND (conjunction)
    case AnyMayMatch  = 'OR';  // logical OR (disjunction)
}
