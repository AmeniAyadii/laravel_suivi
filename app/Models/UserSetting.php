<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    protected $fillable = [
        'user_id',
        'notifications_enabled',
        'medication_reminders',
        'appointment_reminders',
        'health_tips_enabled',
        'data_sync_enabled',
        'biometric_enabled',
        'language',
        'theme',
        'font_size',
        'font_size_value',
    ];

    protected $casts = [
        'notifications_enabled' => 'boolean',
        'medication_reminders' => 'boolean',
        'appointment_reminders' => 'boolean',
        'health_tips_enabled' => 'boolean',
        'data_sync_enabled' => 'boolean',
        'biometric_enabled' => 'boolean',
        'font_size_value' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
