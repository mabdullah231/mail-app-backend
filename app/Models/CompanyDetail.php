<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyDetail extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'logo', 'signature', 'address'];

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
}
