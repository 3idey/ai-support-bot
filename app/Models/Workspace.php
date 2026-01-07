<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Workspace extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'public_key',
    ];
    public function users()
    {
        return $this->belongsToMany(User::class);
    }
    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }
}
