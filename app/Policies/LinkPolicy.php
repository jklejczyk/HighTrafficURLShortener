<?php

namespace App\Policies;

use App\Models\Link;
use App\Models\User;

class LinkPolicy
{
    public function update(?User $user, Link $link): bool
    {
        if ($link->user_id === null) {
            return true;
        }

        return $user !== null && $link->user_id === $user->id;
    }

    public function delete(?User $user, Link $link): bool
    {
        return $this->update($user, $link);
    }
}
