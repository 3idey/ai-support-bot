<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = [
        'workspace_id',
        'title',
        'source_type',
        'file_path',
        'chunk_count',
        'processed',
        'status',
        'error_message',
    ];
    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function chunks()
    {
        return $this->hasMany(DocumentChunk::class);
    }
}
