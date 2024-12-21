<?php

declare(strict_types=1);

namespace YouMixx\SleepingowlSyncForm\Http;

use Exception;
use Illuminate\Http\JsonResponse;
use SleepingOwl\Admin\Contracts\ModelConfigurationInterface;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use YouMixx\SleepingowlSyncForm\Objects\SyncableObject;
use YouMixx\SleepingowlSyncForm\Service\SycnableService;

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

        $sycnable = new SycnableService($model);
        $sycnable->setItem($item)->syncable();

        if ($sycnable->isFailed()) {
            $urls = implode(', ', $sycnable->getErrors());

            return redirect($request->input('_redirectBack', back()->getTargetUrl()))
                ->with('error_message', 'Ошибка синхронизации: не удалось установить связь с: ' . $urls);
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

        // logger('Webhook Before Data', ['data' => $data]);

        // After modifications (before created)
        $data = $syncableObject->afterSyncableColumns('before')->apply();

        // logger('Webhook After Data', ['data' => $data]);

        DB::transaction(function () use ($model, $data, $key, $syncableObject) {
            $eloquentModel = $model->getRepository()->getModel()->updateOrCreate([
                $key => $data[$key]
            ], $data);

            // After modifications (after created)
            $syncableObject = $syncableObject->setEloquentModel($eloquentModel);
            $syncableObject->afterSyncableColumns('after')->apply();
        });

        return response()->json([
            'status' => true,
        ]);
    }
}
