<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id','customer_id','template_id','status','response','sent_at'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    public function company()
    {
        return $this->belongsTo(CompanyDetail::class, 'company_id');
    }
}
