<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'reporter_customer_id',
        'reported_customer_id',
        'review_id',
        'reason',
        'details',
        'status',
    ];

    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', 'pending');
    }

    // Relationships
    public function reporter()
    {
        return $this->belongsTo(Customer::class, 'reporter_customer_id');
    }

    public function reportedCustomer()
    {
        return $this->belongsTo(Customer::class, 'reported_customer_id');
    }

    public function review()
    {
        return $this->belongsTo(Review::class);
    }
}