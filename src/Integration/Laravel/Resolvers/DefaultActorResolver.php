<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel\Resolvers;

use Illuminate\Http\Request;
use Tetthys\Cake\Integration\Laravel\Contracts\ActorResolver;
use Tetthys\Cake\Model\Actor;

final class DefaultActorResolver implements ActorResolver
{
    public function fromRequest(Request $request): Actor
    {
        $user = $request->user();

        // English comment: Guest actor => null id and 'guest' role
        if ($user === null) {
            return new Actor(
                id: null,
                roles: ['guest'],
                attrs: [
                    'is_authenticated' => false,
                    'subject' => null,
                ],
            );
        }

        // English comment: Laravel auth identifier is the canonical subject id
        $id = (string) $user->getAuthIdentifier();

        // English comment: Resolve roles with common Laravel patterns (fast path)
        $roles = $this->resolveRolesFast($user);
        if ($roles === []) {
            $roles = ['user'];
        }

        return new Actor(
            id: $id,
            roles: $roles,
            attrs: [
                'is_authenticated' => true,
                'subject' => $user,
            ],
        );
    }

    /**
     * Resolve roles from the most common patterns only.
     *
     * @return list<string>
     */
    private function resolveRolesFast(object $user): array
    {
        // 1) spatie/permission: getRoleNames()
        if (method_exists($user, 'getRoleNames')) {
            /** @var mixed $names */
            $names = $user->getRoleNames();

            // English comment: getRoleNames() is usually a Collection
            if (is_object($names) && method_exists($names, 'all')) {
                $names = $names->all();
            } elseif (is_object($names) && method_exists($names, 'toArray')) {
                $names = $names->toArray();
            }

            if (is_array($names)) {
                return $this->normalizeStrings($names);
            }
        }

        // 2) $user->role (string)
        if (property_exists($user, 'role') && is_string($user->role) && $user->role !== '') {
            return [$user->role];
        }

        // 3) $user->roles (array<string>)
        if (property_exists($user, 'roles') && is_array($user->roles)) {
            return $this->normalizeStrings($user->roles);
        }

        return [];
    }

    /**
     * @param array<mixed> $values
     * @return list<string>
     */
    private function normalizeStrings(array $values): array
    {
        $out = [];
        foreach ($values as $v) {
            $s = is_string($v) ? $v : (is_scalar($v) ? (string) $v : '');
            if ($s !== '') {
                $out[] = $s;
            }
        }

        // English comment: Deduplicate while keeping order
        $out = array_values(array_unique($out));

        return $out;
    }
}
