<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'template_id',
        'event_type',
        'timing',
        'channel',
        'recurring',
        'recurrence_interval',
        'active',
        'rules',
    ];

    protected $casts = [
        'rules' => 'array',
        'recurring' => 'boolean',
        'active' => 'boolean',
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