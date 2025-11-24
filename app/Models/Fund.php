<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fund extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'funds';

    protected $guarded = [];

    public function pettyCashes()
    {
        return $this->hasMany(PettyCash::class, 'fund_id');
    }

    public function recordBooks()
    {
        return $this->hasMany(RecordBook::class);
    }
}
