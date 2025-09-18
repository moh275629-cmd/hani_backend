<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchEditRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'action',
        'branch_id',
        'branch_data',
        'status',
        'requested_at',
        'processed_at',
        'processed_by',
        'admin_notes',
    ];

    protected $casts = [
        'branch_data' => 'array',
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function branch()
    {
        return $this->belongsTo(StoreBranch::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
