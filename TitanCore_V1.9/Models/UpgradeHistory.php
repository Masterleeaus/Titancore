<?php

namespace Modules\TitanCore\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Represents a single completed (or failed) module upgrade run.
 *
 * @property int         $id
 * @property string      $module_name
 * @property string      $version
 * @property array|null  $files_applied
 * @property string|null $snapshot_path
 * @property string      $status          success | failed | rolled_back
 * @property string|null $error_detail
 * @property \Carbon\Carbon|null $applied_at
 * @property \Carbon\Carbon      $created_at
 * @property \Carbon\Carbon      $updated_at
 */
class UpgradeHistory extends Model
{
    protected $table = 'upgrade_history';

    protected $fillable = [
        'module_name',
        'version',
        'files_applied',
        'snapshot_path',
        'status',
        'error_detail',
        'applied_at',
    ];

    protected $casts = [
        'files_applied' => 'array',
        'applied_at'    => 'datetime',
    ];

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForModule($query, string $moduleName)
    {
        return $query->where('module_name', $moduleName);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
