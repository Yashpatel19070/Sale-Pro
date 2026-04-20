<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\PipelineStage;
use App\Enums\UnitJobStatus;
use App\Models\PoUnitJob;
use App\Models\User;

class PoUnitJobPolicy
{
    public function createJob(User $user): bool
    {
        return $user->can(Permission::PIPELINE_RECEIVE);
    }

    public function start(User $user, PoUnitJob $job): bool
    {
        return $job->status === UnitJobStatus::Pending
            && $this->hasStagePermission($user, $job->current_stage);
    }

    public function pass(User $user, PoUnitJob $job): bool
    {
        return $job->status === UnitJobStatus::InProgress
            && $job->assigned_to_user_id === $user->id
            && $this->hasStagePermission($user, $job->current_stage);
    }

    public function fail(User $user, PoUnitJob $job): bool
    {
        return $job->status === UnitJobStatus::InProgress
            && $job->assigned_to_user_id === $user->id
            && $this->hasStagePermission($user, $job->current_stage);
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PIPELINE_VIEW_ANY);
    }

    public function view(User $user, PoUnitJob $job): bool
    {
        return $user->can(Permission::PIPELINE_VIEW_ANY)
            || $this->hasStagePermission($user, $job->current_stage);
    }

    private function hasStagePermission(User $user, PipelineStage $stage): bool
    {
        return match ($stage) {
            PipelineStage::Receive => $user->can(Permission::PIPELINE_RECEIVE),
            PipelineStage::Visual => $user->can(Permission::PIPELINE_VISUAL),
            PipelineStage::SerialAssign => $user->can(Permission::PIPELINE_SERIAL_ASSIGN),
            PipelineStage::Tech => $user->can(Permission::PIPELINE_TECH),
            PipelineStage::Qa => $user->can(Permission::PIPELINE_QA),
            PipelineStage::Shelf => $user->can(Permission::PIPELINE_SHELF),
        };
    }
}
