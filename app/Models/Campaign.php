<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $fillable = [
        'created_by', 'campaign_name', 'status', 'delivery',
        'results', 'result_type', 'cost_per_result', 'cost_per_result_type',
        'budget', 'budget_type', 'amount_spent', 'impressions', 'reach',
        'ends', 'attribution_setting', 'bid_strategy',
        'total_messaging', 'new_messaging', 'purchases', 'cost_per_purchase',
        'purchases_conversion_value', 'purchase_roas',
        'cost_per_new_messaging', 'messaging_conversations', 'cost_per_messaging',
        'orders_created', 'orders_shipped',
        'date_range_start', 'date_range_end',
    ];

    protected $casts = [
        'created_by' => 'integer',
        'results' => 'integer',
        'impressions' => 'integer',
        'reach' => 'integer',
        'total_messaging' => 'integer',
        'new_messaging' => 'integer',
        'purchases' => 'integer',
        'messaging_conversations' => 'integer',
        'orders_created' => 'integer',
        'orders_shipped' => 'integer',
        'cost_per_result' => 'float',
        'budget' => 'float',
        'amount_spent' => 'float',
        'cost_per_purchase' => 'float',
        'purchases_conversion_value' => 'float',
        'purchase_roas' => 'float',
        'cost_per_new_messaging' => 'float',
        'cost_per_messaging' => 'float',
        'date_range_start' => 'date',
        'date_range_end' => 'date',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CampaignAssignment::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(CampaignGroup::class, 'campaign_group_items')
            ->withPivot('sort_order')
            ->withTimestamps();
    }
}
