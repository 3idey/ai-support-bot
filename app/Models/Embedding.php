<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Embedding extends Model
{
    protected $fillable = [
        'document_chunk_id',
        'embedding',
        'similarity',
    ];
    protected $casts = [
        'embedding' => 'array',
    ];
    public function documentChunk()
    {
        return $this->belongsTo(DocumentChunk::class, 'document_chunk_id');
    }
}
