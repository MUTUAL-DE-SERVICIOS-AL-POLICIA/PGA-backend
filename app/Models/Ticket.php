<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'store.tickets';

    protected $fillable = [
        'pettycash_id',
        'id_permission',   
        'from',
        'to',
        'cost',
        'ticket_invoice',
        'group_id',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function pettyCash()
    {
        return $this->belongsTo(PettyCash::class, 'pettycash_id', 'id');
    }

    public function departure()
    {
        return $this->belongsTo(Departure::class, 'id_permission', 'id');
    }
}
