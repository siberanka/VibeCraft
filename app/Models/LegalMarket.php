<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $country
 * @property string $subdivision
 * @property array<string,string> $trader
 * @property list<string> $locales
 * @property bool $is_enabled
 * @property int $revision
 * @property Carbon|null $reviewed_at
 */
class LegalMarket extends Model
{
    protected $fillable = ['country', 'subdivision', 'trader', 'locales'];

    protected $attributes = ['subdivision' => '', 'is_enabled' => false, 'revision' => 1];

    protected function casts(): array
    {
        return ['trader' => 'array', 'locales' => 'array', 'is_enabled' => 'boolean', 'revision' => 'integer', 'reviewed_at' => 'datetime'];
    }

    /** @return HasMany<LegalPolicy, $this> */
    public function policies(): HasMany
    {
        return $this->hasMany(LegalPolicy::class);
    }
}
