<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 
        'name', 
        'email',
        'phone', 
        'country',
        'address',
        'business_email',
        'business_email_password',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'logo', 
        'signature'
    ];

    protected $hidden = [
        'business_email_password'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class, 'company_id');
    }

    public function templates()
    {
        return $this->hasMany(Template::class, 'company_id');
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class, 'company_id');
    }

    /**
     * Check if company has email configuration
     */
    public function hasEmailConfiguration()
    {
        return !empty($this->business_email) && 
               !empty($this->smtp_host) && 
               !empty($this->smtp_port);
    }

    /**
     * Get full logo URL
     */
    public function getLogoUrlAttribute()
    {
        return $this->logo ? url($this->logo) : null;
    }

    /**
     * Get full signature URL
     */
    public function getSignatureUrlAttribute()
    {
        return $this->signature ? url($this->signature) : null;
    }
}