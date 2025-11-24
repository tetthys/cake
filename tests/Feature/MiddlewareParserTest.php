<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Tetthys\Cake\Integration\Laravel\MiddlewareParser;
use Tetthys\Cake\Rule\RuleSet;

// -------------------------
// Dummy Policies (global)
// -------------------------

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

// ---------------------------------------------------------
// Tests (NO namespace; only hooks)
// ---------------------------------------------------------

describe('MiddlewareParser (test hooks only)', function () {

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
