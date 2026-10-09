<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\Badge;
use Inertia\Inertia;

trait CelebratesBadges
{
    /**
     * Selos recém-conquistados viram uma comemoração na próxima página.
     *
     * @param  list<Badge>  $badges
     */
    protected function celebrate(array $badges): void
    {
        if ($badges === []) {
            return;
        }

        Inertia::flash('badges', array_map(fn (Badge $b) => [
            'badge' => $b->value,
            'label' => $b->label(),
            'emoji' => $b->emoji(),
        ], $badges));
    }
}
