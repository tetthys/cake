<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Tetthys\Cake\Integration\Laravel\MiddlewareParser;
use Tetthys\Cake\Rule\RuleSet;

// -------------------------
// Dummy Policies (global)
// -------------------------

// First candidate (object-based) — class exists but NO "access" method.
class SellerRules
{
    public function index(Request $req): RuleSet
    {
        return new RuleSet([]);
    }
}

// Second candidate (resource-based) — has "access".
class SellerReportRules
{
    public function access(Request $req): RuleSet
    {
        return new RuleSet([]);
    }
}

class PostRules
{
    public function update(Request $req): RuleSet
    {
        return new RuleSet([]);
    }
}

class BadRules
{
    public function index(Request $req): string
    {
        return 'not-ruleset';
    }
}

// Dummy domain object to force object-based candidate = SellerRules first.
class Seller {}

// ---------------------------------------------------------
// Tests (NO namespace; only hooks)
// ---------------------------------------------------------

describe('MiddlewareParser (test hooks only)', function () {

    it('falls back to next candidate if method missing on earlier candidate', function () {
        $instantiated = [];

        $parser = MiddlewareParser::forTesting(
            policyNamespaces: ['App\\Policies'],
            classExistsHook: fn(string $class) =>
            in_array($class, [SellerRules::class, SellerReportRules::class], true),

            makePolicyHook: function (string $class) use (&$instantiated) {
                $instantiated[] = $class;
                return new $class();
            },

            candidateTransformHook: fn(string $candidate) => class_basename($candidate),
        );

        $req = Request::create('/');
        $sellerObj = new Seller();

        // Candidates order:
        // 1) App\Policies\SellerRules  (exists, but no access method) -> must skip
        // 2) App\Policies\SellerReportRules (exists, has access) -> should use
        $rules = $parser->resolveRulesAuto($req, 'sellerReport.access', $sellerObj);

        expect($rules)->toBeInstanceOf(RuleSet::class);
        expect($instantiated)->toBe([SellerRules::class, SellerReportRules::class]);
    });

    it('infers policy from action resource', function () {
        $parser = MiddlewareParser::forTesting(
            policyNamespaces: ['App\\Policies'],
            classExistsHook: fn(string $class) => $class === SellerReportRules::class,
            candidateTransformHook: fn(string $candidate) => class_basename($candidate), // App\Policies\XRules => XRules
        );

        [$class, $method] = $parser->inferPolicy('sellerReport.access');

        expect($class)->toBe(SellerReportRules::class);
        expect($method)->toBe('access');
    });

    it('normalizes camel/snake/kebab consistently', function () {
        $parser = MiddlewareParser::forTesting(
            classExistsHook: fn(string $class) => $class === SellerReportRules::class,
            candidateTransformHook: fn(string $candidate) => class_basename($candidate),
        );

        expect($parser->inferPolicy('sellerReport.access')[0])->toBe(SellerReportRules::class);
        expect($parser->inferPolicy('seller_report.access')[0])->toBe(SellerReportRules::class);
        expect($parser->inferPolicy('seller-report.access')[0])->toBe(SellerReportRules::class);
    });

    it('prefers object-based candidate when available', function () {
        $parser = MiddlewareParser::forTesting(
            classExistsHook: fn(string $class) => $class === PostRules::class,
            candidateTransformHook: fn(string $candidate) => class_basename($candidate),
        );

        $obj = new class {
            // class_basename => "class@anonymous" (not Post)
            // but action resource still yields PostRules candidate
        };

        [$class, $method] = $parser->inferPolicy('post.update', $obj);

        expect($class)->toBe(PostRules::class);
        expect($method)->toBe('update');
    });

    it('resolveRulesAuto returns RuleSet', function () {
        $parser = MiddlewareParser::forTesting(
            classExistsHook: fn(string $class) => $class === SellerReportRules::class,
            makePolicyHook: fn(string $class) => new $class(),
            candidateTransformHook: fn(string $candidate) => class_basename($candidate),
        );

        $req = Request::create('/');

        $rules = $parser->resolveRulesAuto($req, 'sellerReport.access');

        expect($rules)->toBeInstanceOf(RuleSet::class);
    });

    it('resolveRulesAuto throws TypeError when invalid return', function () {
        $parser = MiddlewareParser::forTesting(
            classExistsHook: fn(string $class) => $class === BadRules::class,
            makePolicyHook: fn(string $class) => new $class(),
            candidateTransformHook: fn(string $candidate) => class_basename($candidate),
        );

        $req = Request::create('/');

        expect(fn() => $parser->resolveRulesAuto($req, 'bad.index'))
            ->toThrow(TypeError::class);
    });
});
