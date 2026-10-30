<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code_hash
 * @property string $code_hint
 * @property int $amount_minor
 * @property int $max_redemptions
 * @property int $redemptions
 * @property Carbon|null $expires_at
 * @property bool $is_active
 */
class GiftCode extends Model
{
    protected $fillable = ['code_hash', 'code_hint', 'amount_minor', 'max_redemptions', 'expires_at', 'is_active', 'created_by', 'note'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'max_redemptions' => 'integer', 'redemptions' => 'integer', 'expires_at' => 'datetime', 'is_active' => 'boolean'];
    }

    /** @return HasMany<GiftCodeRedemption, $this> */
    public function redemptionRecords(): HasMany
    {
        return $this->hasMany(GiftCodeRedemption::class);
    }
}
