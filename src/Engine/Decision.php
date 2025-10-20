<?php

declare(strict_types=1);

namespace Tetthys\Cake\Engine;

/**
 * Decision is either PERMIT or DENY, with trace for audit/explainability.
 * Deny-by-default is enforced by Engine when no rule matches.
 */
final class Decision
{
    public const PERMIT = "PERMIT";
    public const DENY = "DENY";

    /** @param string[] $trace rule names/reasons checked/selected */
    private function __construct(
        public readonly string $outcome,
        public readonly array $trace = [],
        public readonly ?string $selectedRule = null,
    ) {}

    public static function permit(string $ruleName, array $trace = []): self
    {
        return new self(self::PERMIT, $trace, $ruleName);
    }

    public static function deny(array $trace = []): self
    {
        return new self(self::DENY, $trace, null);
    }

    public function isPermit(): bool
    {
        return $this->outcome === self::PERMIT;
    }
}
