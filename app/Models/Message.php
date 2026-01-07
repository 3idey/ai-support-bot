<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'sources',
        'content',
        'role',
    ];
    protected $casts = [
        'sources' => 'array',
    ];
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
