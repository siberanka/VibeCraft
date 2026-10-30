<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BridgeNonce extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['game_server_id', 'nonce', 'created_at'];
}
