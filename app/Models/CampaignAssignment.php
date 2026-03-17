<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignAssignment extends Model
{
    protected $fillable = [
        'campaign_id', 'student_id', 'decision', 'reasoning',
        'decided_at', 'mentor_feedback', 'score',
    ];

    protected $casts = [
        'campaign_id' => 'integer',
        'student_id' => 'integer',
        'score' => 'integer',
        'decided_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
