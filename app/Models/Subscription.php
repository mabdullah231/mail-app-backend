<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'plan_type', 'amount', 'starts_at', 'expires_at', 
        'remove_branding', 'limits', 'status', 'payment_id'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'limits' => 'array',
        'remove_branding' => 'boolean'
    ];

    public function company()
    {
        return $this->belongsTo(CompanyDetail::class, 'company_id');
    }

    public function isActive()
    {
        return $this->status === 'active' && 
               ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function canRemoveBranding()
    {
        return $this->remove_branding && $this->isActive();
    }

    public function getEmailLimit()
    {
        return $this->limits['emails_per_month'] ?? ($this->plan_type === 'free' ? 100 : 10000);
    }

    public function getSmsLimit()
    {
        return $this->limits['sms_per_month'] ?? ($this->plan_type === 'free' ? 10 : 1000);
    }

    public function getTemplateLimit()
    {
        return $this->limits['templates'] ?? ($this->plan_type === 'free' ? 3 : 50);
    }
}
