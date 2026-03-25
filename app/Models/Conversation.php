<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_PENDING = 'pending';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'workspace_id',
        'contact_id',
        'assigned_agent_id',
        'channel',
        'subject',
        'status',
        'priority',
        'last_message_at',
        'metadata',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        // JSON column: lets us store arbitrary per-channel context
        // (e.g. Shopify order id, referring page) without schema churn.
        'metadata' => 'array',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Scope: only conversations in the given workspace.
     * Keeps tenant isolation in one place instead of scattering
     * where('workspace_id', ...) across every query.
     */
    public function scopeForWorkspace(Builder $query, int $workspaceId): Builder
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    /**
     * Scope: unassigned + open, ordered oldest-first. This is the
     * "who needs a human now" queue an agent dashboard would poll.
     */
    public function scopeUnassignedQueue(Builder $query): Builder
    {
        return $query->whereNull('assigned_agent_id')
            ->where('status', self::STATUS_OPEN)
            ->orderBy('last_message_at');
    }

    /**
     * JSON-based query helper: filter conversations by a value stored
     * inside the metadata JSON column (MySQL/Postgres JSON path).
     */
    public function scopeWhereMetadata(Builder $query, string $key, $value): Builder
    {
        return $query->where("metadata->{$key}", $value);
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }
}
