<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftCodeRedemption extends Model
{
    protected $fillable = ['gift_code_id', 'user_id', 'wallet_entry_id', 'amount_minor'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }
}
