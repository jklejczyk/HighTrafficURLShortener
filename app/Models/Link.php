<?php

namespace App\Models;

use App\Observers\LinkObserver;
use Database\Factories\LinkFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([LinkObserver::class])]
class Link extends Model
{
    /** @use HasFactory<LinkFactory> */
    use HasFactory;

    protected $table = 'links';

    protected $fillable = ['short_code', 'original_url', 'user_id', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
