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

        return new Actor(
            id: (string) ($user?->getAuthIdentifier() ?? "guest"),
            roles: (array) ($user?->roles?->toArray() ?? []),
            attrs: [
                "is_authenticated" => (bool) $user,
                // Pass the full User model so predicates can call methods like isAdmin()
                "subject" => $user,
            ],
        );
    }
}
