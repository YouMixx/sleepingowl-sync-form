<?php

declare(strict_types=1);

namespace YouMixx\SleepingowlSyncForm\Service;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use SleepingOwl\Admin\Contracts\ModelConfigurationInterface;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SleepingOwl\Admin\Model\ModelCollection;
use YouMixx\SleepingowlSyncForm\Objects\SyncableObject;

class SycnableService
{
    /**
     * @var ModelConfigurationInterface
     */
    protected $model;

    /**
     * @var Model[]|Collection
     */
    protected $items;

    /**
     * @var array
     */
    protected $errors = [];

    /**
     * @var array
     */
    protected $result = [];

    public function __construct(ModelConfigurationInterface $model)
    {
        $this->model = $model;

        $this->items = new Collection();
    }

    public function isSuccessfull(): bool
    {
        return (bool) ! count($this->errors);
    }

    public function isFailed(): bool
    {
        return (bool) count($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getResult(): array
    {
        return $this->result;
    }

    public function setItem(Model $model): self
    {
        $this->items->add($model);

        return $this;
    }

    public function setItems(Collection $models): self
    {
        $this->items = $models;

        return $this;
    }

    /**
     * @return $this
     */
    public function syncable(): self
    {
        foreach ($this->items as $item) {
            $this->execute($item);
        }

        return $this;
    }

    private function execute(Model $item): bool
    {
        $key = $this->model->getUniqueSyncKey();
        $columns = $this->model->syncableColumns();
        $allColumns = array_merge([$key], $columns);
        $data = $item->only($allColumns);

        // Before modifications
        $syncableObject = new SyncableObject($this->model, $data);
        $data = $syncableObject->beforeSyncableColumns()->apply();

        $syncUrls = config('sleeping_owl.sync_urls');
        foreach ($syncUrls as $url) {
            if (str_contains($url, config('app.url'))) continue;

            try {
                $response = Http::withoutVerifying()
                    ->withHeaders([
                        'key' => config('sleeping_owl.sync_key')
                    ])->post($url . config('sleeping_owl.url_prefix') . "webhook/" . $this->model->getAlias() . '/sync', [
                        'key' => $key,
                        'data' => $data,
                    ]);

                // Log::withContext(['response' => $response->body()]);

                if ($response->json()['status'] != true) throw new Exception('Not ok');
            } catch (\Throwable $th) {
                report($th);

                $this->errors[] = $url;
                $this->result[$item->id][$url] = [
                    'status' => false,
                    'message' => $th->getMessage(),
                ];
            }

            $this->result[$item->id][$url] = [
                'status' => true
            ];
        }

        return true;
    }
}
