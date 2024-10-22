<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Photo extends Model
{
    protected $fillable = ['path', 'email', 'checked'];

    protected $appends = ['url'];

    protected $casts = [
        'checked' => 'boolean',
    ];

    public function getUrlAttribute()
    {
        return \Illuminate\Support\Facades\Storage::url($this->path);
    }

}