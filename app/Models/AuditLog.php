<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Catatan kegiatan penting (tabel `audit_logs`). Immutable: tidak dapat
 * diubah atau dihapus melalui Eloquent. `actor_id` tidak fillable.
 * metadata tidak boleh memuat password, hash, cookie, atau token.
 */
#[Table('audit_logs')]
#[Fillable(['action', 'subject_type', 'subject_id', 'metadata'])]
class AuditLog extends ModelDomain
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'subject_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log tidak dapat diubah.'));
        static::deleting(fn () => throw new LogicException('Audit log tidak dapat dihapus.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
