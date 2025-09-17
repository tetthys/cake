<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel\Contracts;

use Illuminate\Http\Request;
use Tetthys\Cake\Model\Actor;

interface ActorResolver
{
    /** Build an Actor from the current HTTP request. */
    public function fromRequest(Request $request): Actor;
}
