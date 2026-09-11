<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\CommunicationMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message within a {@see CommunicationThread}. School-owned
 * ({@see BelongsToSchool}). Every message is authored by a staff `User` in
 * this milestone — see `docs/communication.md`. Never hard-deleted.
 */
class CommunicationMessage extends Model
{
    /** @use HasFactory<CommunicationMessageFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'communication_thread_id',
        'body',
    ];

    /**
     * @return BelongsTo<CommunicationThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(CommunicationThread::class, 'communication_thread_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
