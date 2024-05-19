<?php

namespace App\Models\Podcast;

use App\Casts\Podcast\PodcastStateCast;
use App\Values\Podcast\PodcastState;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property Carbon $created_at
 * @property PodcastState $state
 */
class PodcastUserPivot extends Pivot
{
    protected $table = 'podcast_user';

    protected $guarded = [];
    protected $appends = ['meta'];

    protected $casts = [
        'state' => PodcastStateCast::class,
    ];
}
