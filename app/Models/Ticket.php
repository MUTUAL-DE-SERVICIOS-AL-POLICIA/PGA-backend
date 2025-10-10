<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function pettyCash()
    {
        return $this->belongsTo(PettyCash::class);
    }

}
