<?php

declare(strict_types=1);

namespace YouMixx\SleepingowlSyncForm\Model;

use Illuminate\Database\Eloquent\Model;
use SleepingOwl\Admin\Contracts\ModelConfigurationInterface;
use SleepingOwl\Admin\Model\SectionModelConfiguration;

class SyncSectionModelConfiguration extends SectionModelConfiguration implements ModelConfigurationInterface
{
    /**
     * @var string|null
     */
    protected $uniqueSyncKey = null;

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    public function isSyncable(Model $model)
    {
        return method_exists($this, 'syncableColumns') && $this->uniqueSyncKey;
    }

    public function getUniqueSyncKey(): string
    {
        return $this->uniqueSyncKey;
    }
}
