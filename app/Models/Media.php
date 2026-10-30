<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $fillable = ['uploaded_by', 'disk', 'library_pack', 'library_category', 'path', 'original_name', 'mime', 'size', 'width', 'height', 'alt', 'sha256'];

    public function url(): string
    {
        return route('media.show', ['media' => $this->id, 'v' => substr($this->sha256, 0, 10)]);
    }
}
