<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'user_id';

    protected $fillable = ['user_id', 'balance_minor'];

    protected function casts(): array
    {
        return ['balance_minor' => 'integer'];
    }
}
