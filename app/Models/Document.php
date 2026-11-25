<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    //

    protected $fillable = [
        'user_id',
        'original_name',
        'storage_path',
        'mime_type',
        'size',
        'sha256', # file signature
        'status', # quarantined -> approve or rejected (after scanning)
        'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

}
