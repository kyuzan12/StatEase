<?php

namespace App\Policies;

use App\Models\Survey;
use App\Models\User;

/**
 * Who may do what with a survey: only the user who uploaded it.
 * Used by the routes (middleware "can:view,survey"); anyone else gets "403 Forbidden".
 */
class SurveyPolicy
{
    public function view(User $user, Survey $survey): bool
    {
        return $survey->user_id === $user->id;
    }

    public function update(User $user, Survey $survey): bool
    {
        return $survey->user_id === $user->id;
    }

    public function delete(User $user, Survey $survey): bool
    {
        return $survey->user_id === $user->id;
    }
}
