<?php

declare(strict_types=1);

namespace YouMixx\SleepingowlSyncForm\Objects;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use SleepingOwl\Admin\Contracts\ModelConfigurationInterface;

class SyncableObject
{
    protected ModelConfigurationInterface $model;
    protected Collection $data;
    protected array $modifiers;
    protected $eloquentModel;

    public function __construct(ModelConfigurationInterface $model, array $data, Model $eloquentModel = null)
    {
        $this->model = $model;
        $this->eloquentModel = $eloquentModel;

        $this->setData($data);
    }

    public function setData(array $data): self
    {
        $this->data = collect($data)->only($this->model->syncableColumns());

        return $this;
    }

    public function getData(): Collection
    {
        return $this->data;
    }

    public function setEloquentModel(Model $eloquentModel): self
    {
        $this->eloquentModel = $eloquentModel;

        return $this;
    }

    public function beforeSyncableColumns(): self
    {
        $this->modifiers = $this->model->modificationBeforeSyncableColumns();

        return $this;
    }

    public function afterSyncableColumns($type = 'before'): self
    {
        $this->modifiers = $this->model->modificationAfterSyncableColumns($this->eloquentModel)[$type];

        return $this;
    }

    public function apply(): array
    {
        return collect($this->getData())->map(function ($element, $key) {
            if ($element !== null && array_key_exists($key, $this->modifiers)) {
                return call_user_func($this->modifiers[$key], $element);
            }

            return $element;
        })
            ->filter(fn($element) => $element !== 'unset')
            ->toArray();
    }
}
