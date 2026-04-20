<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PipelineStage;
use App\Enums\UnitEventAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoUnitEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'po_unit_job_id',
        'stage',
        'action',
        'user_id',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'stage' => PipelineStage::class,
            'action' => UnitEventAction::class,
            'created_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(PoUnitJob::class, 'po_unit_job_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
