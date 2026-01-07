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

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($workspace) {
            if (empty($workspace->slug)) {
                $workspace->slug = \Illuminate\Support\Str::slug($workspace->name);
            }
            if (empty($workspace->public_key)) {
                $workspace->public_key = \Illuminate\Support\Str::random(32);
            }
        });
    }


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
