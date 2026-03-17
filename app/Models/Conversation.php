<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = ['user_id', 'customer_type_id', 'product_id', 'customer_name', 'status', 'mentor_feedback', 'mentor_score'];

    protected $casts = [
        'updated_at' => 'datetime',
        'mentor_score' => 'integer',
    ];

    public function customerType(): BelongsTo
    {
        return $this->belongsTo(CustomerType::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
