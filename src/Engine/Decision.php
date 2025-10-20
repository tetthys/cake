<?php

declare(strict_types=1);

namespace Tetthys\Cake\Engine;

/**
 * Decision is either PERMIT or DENY, with trace for audit/explainability.
 * Deny-by-default is enforced by Engine when no rule matches.
 */
final readonly class Decision
{
    public const PERMIT = "PERMIT";
    public const DENY = "DENY";

    /**
     * @param string        $outcome       One of self::PERMIT|self::DENY
     * @param array<int,string> $trace     Evaluation steps (e.g. "[Rule] match/no-match")
     * @param string|null   $selectedRule  The rule name that permitted, if any
     */
    private function __construct(
        public string $outcome,
        public array $trace = [],
        public ?string $selectedRule = null,
    ) {}

    /** Factory: PERMIT with selected rule name and trace. */
    public static function permit(string $ruleName, array $trace = []): self
    {
        return new self(self::PERMIT, $trace, $ruleName);
    }

    /** Factory: DENY with trace. */
    public static function deny(array $trace = []): self
    {
        return new self(self::DENY, $trace);
    }

    /** True when outcome is PERMIT. */
    public function isPermit(): bool
    {
        return $this->outcome === self::PERMIT;
    }

    /** True when outcome is DENY. */
    public function isDeny(): bool
    {
        return $this->outcome === self::DENY;
    }
}
