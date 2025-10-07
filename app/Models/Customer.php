<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id','name','email','phone','address','country',
        'sms_opt_in','notification','template_id','frequency',
        'reminder_start_date','notification_rules',
        'unsubscribe_option'
    ];
    
    protected $casts = [
        'notification_rules' => 'array',
        'sms_opt_in' => 'boolean',
        'unsubscribe_option' => 'boolean'
    ];

    public function company()
    {
        return $this->belongsTo(CompanyDetail::class, 'company_id');
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class);
    }
}
