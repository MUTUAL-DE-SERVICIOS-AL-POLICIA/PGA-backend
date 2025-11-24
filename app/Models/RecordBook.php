<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecordBook extends Model
{
    use HasFactory;

    protected $fillable = [
        'action',
        'cost',
        'date',
        'year',
        'fund_id',
        'pettycash_id',
        'incomes',
        'expenses',
        'total',
    ];

    public function fund()
    {
        return $this->belongsTo(Fund::class);
    }

    public function pettycash()
    {
        return $this->belongsTo(PettyCash::class);
    }
}
