<?php

declare(strict_types=1);

namespace YouMixx\SleepingowlSyncForm\Http;

use Exception;
use Illuminate\Http\JsonResponse;
use SleepingOwl\Admin\Contracts\ModelConfigurationInterface;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use YouMixx\SleepingowlSyncForm\Objects\SyncableObject;

class SyncController
{
    /**
     * @param  ModelConfigurationInterface  $model
     * @param  Request  $request
     * @param  int  $id
     * @return RedirectResponse
     */
    public function sync(ModelConfigurationInterface $model, Request $request, $id)
    {
        $item = $model->getRepository()->find($id);

        if (is_null($item) || ! $model->isSyncable($item)) {
            abort(404);
        }

        $key = $model->getUniqueSyncKey();
        $columns = $model->syncableColumns();
        $allColumns = array_merge([$key], $columns);
        $data = $item->only($allColumns);

        // Before modifications
        $syncableObject = new SyncableObject($model, $data);
        $data = $syncableObject->beforeSyncableColumns()->apply();

        $syncUrls = config('sleeping_owl.sync_urls');
        foreach ($syncUrls as $url) {
            // if (str_contains($url, config('app.url'))) continue;

            try {
                $response = Http::withoutVerifying()
                    ->withHeaders([
                        'key' => config('sleeping_owl.sync_key')
                    ])->post($url . config('sleeping_owl.url_prefix') . "webhook/" . $model->getAlias() . '/sync', [
                        'key' => $key,
                        'data' => $data,
                    ]);

                // Log::withContext(['response' => $response->body()]);

                if ($response->json()['status'] != true) throw new Exception('Not ok');
            } catch (\Throwable $th) {
                report($th);
                return redirect($request->input('_redirectBack', back()->getTargetUrl()))
                    ->with('error_message', 'Ошибка синхронизации: не удалось установить связь с: ' . $url);
            }
        }

        return redirect($request->input('_redirectBack', back()->getTargetUrl()))
            ->with('success_message', 'Успешная синхронизация');
    }

    public function webhook(ModelConfigurationInterface $model, Request $request): JsonResponse
    {
        $key = $request->get('key');
        $data = $request->get('data');

        $eloquentModel = $model->getRepository()->getModel();
        $syncableObject = new SyncableObject($model, $data, $eloquentModel);

        logger('Webhook Before Data', ['data' => $data]);

        // After modifications (before created)
        $data = $syncableObject->afterSyncableColumns('before')->apply();

        logger('Webhook After Data', ['data' => $data]);

        /*
        $eloquentModel = $model->getRepository()->getModel()->updateOrCreate([
            $key => $data[$key]
        ], $data);

        // After modifications (after created)
        $syncableObject = $syncableObject->setEloquentModel($eloquentModel);
        $syncableObject->afterSyncableColumns('after')->apply();
        */

        return response()->json([
            'status' => true,
        ]);
    }
}
