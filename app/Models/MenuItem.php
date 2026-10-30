<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    public const LOCATIONS = ['header', 'footer'];

    public const CACHE_KEY = 'vibecraft.menu_items.v1';

    protected $fillable = ['location', 'label', 'url', 'sort', 'is_visible', 'new_tab'];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean', 'new_tab' => 'boolean', 'sort' => 'integer'];
    }
}
