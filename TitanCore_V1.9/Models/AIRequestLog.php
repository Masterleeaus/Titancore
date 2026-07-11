<?php

namespace Modules\TitanCore\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AIRequestLog extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'ai_request_logs';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'module_name',
        'operation_type',
        'model_id',
        'provider_name',
        'model_name',
        'request_prompt',
        'request_options',
        'request_ip',
        'request_user_agent',
        'response_content',
        'response_metadata',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cost',
        'processing_time_ms',
        'status',
        'error_message',
        'error_code',
        'is_flagged',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'request_options' => 'array',
        'response_metadata' => 'array',
        'is_flagged' => 'boolean',
        'reviewed_at' => 'datetime',
        'cost' => 'decimal:6',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'request_ip',
        'request_user_agent',
    ];

    /**
     * Get the user that made the request.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the AI model used for this request.
     */
    public function model()
    {
        return $this->belongsTo(AIModel::class, 'model_id');
    }

    /**
     * Get the admin who reviewed this log.
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope for successful requests.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope for error requests.
     */
    public function scopeErrors($query)
    {
        return $query->where('status', 'error');
    }

    /**
     * Scope for flagged requests.
     */
    public function scopeFlagged($query)
    {
        return $query->where('is_flagged', true);
    }

    /**
     * Scope for unreviewed requests.
     */
    public function scopeUnreviewed($query)
    {
        return $query->whereNull('reviewed_at');
    }

    /**
     * Scope for requests by module.
     */
    public function scopeForModule($query, $module)
    {
        return $query->where('module_name', $module);
    }

    /**
     * Scope for requests by user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Get truncated prompt for display.
     */
    public function getTruncatedPromptAttribute()
    {
        return \Str::limit($this->request_prompt, 100);
    }

    /**
     * Get truncated response for display.
     */
    public function getTruncatedResponseAttribute()
    {
        return \Str::limit($this->response_content, 100);
    }

    /**
     * Check if request needs review.
     */
    public function needsReview()
    {
        return $this->status === 'error' ||
               $this->cost > 1.0 ||
               $this->total_tokens > 4000 ||
               $this->is_flagged;
    }

    /**
     * Mark as reviewed.
     */
    public function markAsReviewed($adminId, $notes = null)
    {
        $this->reviewed_by = $adminId;
        $this->reviewed_at = now();
        if ($notes) {
            $this->admin_notes = $notes;
        }
        $this->save();
    }

    /**
     * Toggle flag status.
     */
    public function toggleFlag()
    {
        $this->is_flagged = ! $this->is_flagged;
        $this->save();
    }
}
