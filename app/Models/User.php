<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\CompanyDetail;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'user_type',
        'email_verified_at',
        'code',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'code' => 'integer',
    ];


    

      public function company_detail()
    {
        return $this->hasOne(CompanyDetail::class, 'user_id');
    }

    public function companyDetail()
    {
        return $this->hasOne(CompanyDetail::class, 'user_id');
    }


}
