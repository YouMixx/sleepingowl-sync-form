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
        if (method_exists($model, 'modificationBeforeSyncableColumns')) {
            $modifiers = $model->modificationBeforeSyncableColumns();

            $data = collect($data)->map(function ($element, $key) use ($modifiers) {
                if (array_key_exists($key, $modifiers)) {
                    return call_user_func($modifiers[$key], $element);
                }

                return $element;
            });
        }

        $syncUrls = config('sleeping_owl.sync_urls');
        foreach ($syncUrls as $url) {
            if (str_contains($url, config('app.url'))) continue;

            try {
                $response = Http::withoutVerifying()
                    ->withHeaders([
                        'key' => config('sleeping_owl.sync_key')
                    ])->post($url . config('sleeping_owl.url_prefix') . "webhook/" . $model->getAlias() . '/sync', [
                        'key' => $key,
                        'data' => $data,
                    ]);

                Log::withContext(['response' => $response->body()]);

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

        $eloquentModel = $model->getRepository()->getModel()->where($key, $data[$key])->first();

        // logger('Webhook Before Data', ['data' => $data]);

        // After modifications
        if (method_exists($model, 'modificationAfterSyncableColumns')) {
            $modifiers = $model->modificationAfterSyncableColumns($eloquentModel);

            $data = collect($data)->map(function ($element, $key) use ($modifiers) {
                if (array_key_exists($key, $modifiers)) {
                    return call_user_func($modifiers[$key], $element);
                }

                return $element;
            })
                ->filter(fn($element) => $element != 'unset')
                ->toArray();
        }

        // logger('Webhook After Data', ['data' => $data]);

        $model->getRepository()->getModel()->updateOrCreate([
            $key => $data[$key]
        ], $data);

        return response()->json([
            'status' => true,
        ]);
    }
}
