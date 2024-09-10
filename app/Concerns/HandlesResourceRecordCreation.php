<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait HandlesResourceRecordCreation
{
    protected function handleRecordCreationWithUniqueField(array $data, Model $model, User $user, ?string $uniqueField = null, ?array $evaluatedTypes = null): Model
    {
        // If evaluatedTypes is provided, ensure the unique field value is within the allowed types
        if ($uniqueField && $evaluatedTypes && ! in_array($data[$uniqueField] ?? '', $evaluatedTypes, true)) {
            $data['enabled'] = false;
            $instance = $model->newInstance($data);
            $instance->save();

            return $instance;
        }

        $companyId = $user->currentCompany->id;
        $shouldBeEnabled = (bool) ($data['enabled'] ?? false);

        $query = $model::query()
            ->where('company_id', $companyId)
            ->where('enabled', true);

        if ($uniqueField && array_key_exists($uniqueField, $data)) {
            $query->where($uniqueField, $data[$uniqueField]);
        }

        $this->toggleRecords($query, $shouldBeEnabled);

        $data['enabled'] = $shouldBeEnabled;
        $instance = $model->newInstance($data);
        $instance->save();

        return $instance;
    }

    private function toggleRecords(Builder $query, bool &$shouldBeEnabled): void
    {
        if ($shouldBeEnabled) {
            $existingEnabledRecord = $query->first();
            $existingEnabledRecord?->update(['enabled' => false]);
        } elseif ($query->doesntExist()) {
            $shouldBeEnabled = true;
        }
    }
}
