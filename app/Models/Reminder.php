<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id','template_id','start_at','recurrence_rule','next_run_at','active'
    ];

    protected $casts = [
        'recurrence_rule' => 'array',
        'active' => 'boolean'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function template()
    {
        return $this->belongsTo(Template::class);
    }
}
