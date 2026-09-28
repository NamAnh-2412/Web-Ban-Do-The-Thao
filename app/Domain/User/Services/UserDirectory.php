<?php

namespace App\Domain\User\Services;

use App\Domain\User\Models\User;
use Illuminate\Support\Collection;

class UserDirectory
{
    /** @param  list<int>  $ids @return Collection<int, User> */
    public function findMany(array $ids): Collection
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }
}
