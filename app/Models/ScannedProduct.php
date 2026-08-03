<?php
// app/Models/ScannedProduct.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScannedProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'barcode',
        'nom',
        'manufacturer',
        'category',
        'sub_category',
        'dosage',
        'product_type',
        'image_url',
        'ingredients',
        'indications',
        'contre_indications',
        'effets_secondaires',
        'notes',
        'expiry_date',
        'source',
        'scanned_at',
    ];

    protected $casts = [
        'ingredients' => 'array',
        'indications' => 'array',
        'contre_indications' => 'array',
        'effets_secondaires' => 'array',
        'expiry_date' => 'date',
        'scanned_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereNull('expiry_date')
            ->orWhere('expiry_date', '>=', now());
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now());
    }

    public function scopeByType($query, $type)
    {
        return $query->where('product_type', $type);
    }

    // Accessors
    public function getIsExpiredAttribute()
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function getFullNameAttribute()
    {
        return $this->nom . ($this->dosage ? ' - ' . $this->dosage : '');
    }
}