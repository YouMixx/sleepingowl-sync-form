<?php

use Illuminate\Support\Facades\Route;
use YouMixx\SleepingowlSyncForm\Http\SyncController;

Route::get('{adminModel}/{adminModelId}/sync', [SyncController::class, 'sync'])
    ->middleware(app()['config']->get('sleeping_owl.middleware'))
    ->name('admin.model.sync');

Route::post('webhook/{adminModel}/sync', [SyncController::class, 'webhook'])
    ->middleware('api');