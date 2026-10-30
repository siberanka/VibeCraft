<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CreditPackage extends Model
{
    protected $fillable = ['name', 'price_minor', 'bonus_minor', 'sort', 'is_visible'];

    protected function casts(): array
    {
        return ['price_minor' => 'integer', 'bonus_minor' => 'integer', 'sort' => 'integer', 'is_visible' => 'boolean'];
    }

    /** @return Collection<int, self> */
    public static function offered(): Collection
    {
        return self::query()->where('is_visible', true)->orderBy('sort')->orderBy('price_minor')->get();
    }

    /** Bonus applies only to the exact price of a visible package; free amounts get none. */
    public static function bonusFor(int $amountMinor): int
    {
        $bonus = (int) self::query()->where('is_visible', true)->where('price_minor', $amountMinor)->max('bonus_minor');

        return max(0, min($bonus, $amountMinor));
    }
}
