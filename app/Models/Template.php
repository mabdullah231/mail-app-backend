<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id','title','body_html','placeholders','attachments','type','is_default'
    ];

    protected $casts = [
        'placeholders' => 'array',
        'attachments' => 'array'
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
