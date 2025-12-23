<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PendingSignup extends Model
{
    use HasFactory;

    protected $fillable = [
        'mobile',
        'normalized_mobile',
        'country_code',
        'firebase_id',
        'type',
        'payload',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Retrieve the decoded payload as an array.
     *
     * @return array<string, mixed>
     */
    public function payloadAsArray(): array
    {
        $decoded = json_decode($this->payload ?? '[]', true);
        return is_array($decoded) ? $decoded : [];
    }
}
