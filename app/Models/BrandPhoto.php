<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrandPhoto extends Model
{
    protected $fillable = ['path', 'source_key', 'source_url', 'alt', 'caption', 'width', 'height', 'published', 'position'];

    protected $casts = ['published' => 'boolean', 'width' => 'integer', 'height' => 'integer'];
}
