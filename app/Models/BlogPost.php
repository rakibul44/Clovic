<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogPost extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['published_at' => 'datetime'];
}
