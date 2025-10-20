<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel\Resolvers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Tetthys\Cake\Integration\Laravel\Contracts\ActorResolver;
use Tetthys\Cake\Model\Actor;

final class DefaultActorResolver implements ActorResolver
{
    public function fromRequest(Request $request): Actor
    {
        $user = $request->user();

        // Resolve subject id
        $id = $this->resolveId($user);

        // Resolve roles safely (array<string>)
        $roles = $this->resolveRoles($user);

        // If unauthenticated, force guest role; otherwise ensure at least 'user'
        if ($user === null) {
            $id = 'guest';
            $roles = ['guest'];
        } elseif ($roles === []) {
            $roles = ['user'];
        }

        return new Actor(
            id: $id,
            roles: $roles,
            attrs: [
                'is_authenticated' => (bool) $user,
                // Expose the subject so predicates may call methods like isAdmin()
                'subject' => $user,
            ],
        );
    }

    private function resolveId(mixed $user): string
    {
        if (!is_object($user)) {
            return 'guest';
        }

        if (method_exists($user, 'getAuthIdentifier')) {
            $v = $user->getAuthIdentifier();
            if ($v !== null) return (string) $v;
        }

        if (method_exists($user, 'getKey')) {
            $v = $user->getKey();
            if ($v !== null) return (string) $v;
        }

        if (property_exists($user, 'id') && $user->id !== null) {
            return (string) $user->id;
        }

        return 'unknown';
    }

    /**
     * Resolve roles from common patterns:
     * - spatie/permission: getRoleNames(): Collection|array|iterable
     * - roles(): Collection|Relation|array|iterable|object with toArray()
     * - public props: roles (array), role (string)
     */
    private function resolveRoles(mixed $user): array
    {
        if (!is_object($user)) {
            return [];
        }

        // 1) getRoleNames()
        if (method_exists($user, 'getRoleNames')) {
            $names = $user->getRoleNames();

            if ($names instanceof Collection) {
                return $this->normalizeStringArray($names->all());
            }
            if (is_object($names) && method_exists($names, 'toArray')) {
                /** @var mixed $arr */
                $arr = $names->toArray();
                return $this->normalizeStringArray($arr);
            }
            if (is_array($names)) {
                return $this->normalizeStringArray($names);
            }
            if (is_iterable($names)) {
                $collected = [];
                foreach ($names as $r) {
                    $collected[] = $this->extractName($r);
                }
                return $this->normalizeStringArray($collected);
            }
        }

        // 2) roles()
        if (method_exists($user, 'roles')) {
            $roles = $user->roles();

            if ($roles instanceof Collection) {
                return $this->normalizeStringArray($roles->all());
            }
            if (is_object($roles) && method_exists($roles, 'toArray')) {
                return $this->normalizeStringArray($roles->toArray());
            }
            if (is_array($roles)) {
                return $this->normalizeStringArray($roles);
            }
            if (is_iterable($roles)) {
                $collected = [];
                foreach ($roles as $r) {
                    $collected[] = $this->extractName($r);
                }
                return $this->normalizeStringArray($collected);
            }
        }

        // 3) public props
        if (property_exists($user, 'roles') && is_array($user->roles)) {
            return $this->normalizeStringArray($user->roles);
        }
        if (property_exists($user, 'role') && is_string($user->role)) {
            return [$user->role];
        }

        return [];
    }

    /**
     * @param mixed $r role item (string|object|scalar)
     */
    private function extractName(mixed $r): string
    {
        // Object with ->name wins
        if (is_object($r) && isset($r->name)) {
            return (string) $r->name;
        }
        return (string) $r;
    }

    /**
     * @param mixed $arr
     * @return array<string>
     */
    private function normalizeStringArray(mixed $arr): array
    {
        if (!is_array($arr)) {
            return [];
        }
        $out = [];
        foreach ($arr as $v) {
            $out[] = $this->extractName($v);
        }
        return array_values(array_unique($out));
    }
}
