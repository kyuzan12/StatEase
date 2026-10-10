<?php

namespace App\Policies;

use App\Models\LikertScale;
use App\Models\User;

/**
 * Built-in scales (no owner) can be used by everyone but changed by nobody,
 * because other users' results depend on them. A custom scale belongs to its creator.
 */
class LikertScalePolicy
{
    public function view(User $user, LikertScale $scale): bool
    {
        return $scale->isBuiltIn() || $scale->user_id === $user->id;
    }

    public function update(User $user, LikertScale $scale): bool
    {
        return ! $scale->isBuiltIn() && $scale->user_id === $user->id;
    }

    public function delete(User $user, LikertScale $scale): bool
    {
        return ! $scale->isBuiltIn() && $scale->user_id === $user->id;
    }
}
