<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = [
        'workspace_id',
        'user_id',
        'visitor_id',
    ];
    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
