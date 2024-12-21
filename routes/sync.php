<?php

use Illuminate\Support\Facades\Route;
use YouMixx\SleepingowlSyncForm\Http\FormSyncController;
use YouMixx\SleepingowlSyncForm\Http\SyncController;

Route::get('{adminModel}/{adminModelId}/sync', [SyncController::class, 'sync'])
    ->middleware(app()['config']->get('sleeping_owl.middleware'))
    ->name('admin.model.sync');

Route::post('webhook/{adminModel}/sync', [SyncController::class, 'webhook'])
    ->middleware('api');

Route::get('{adminModel}/mass-sync', [FormSyncController::class, 'massSycnableForm'])
    ->middleware(app()['config']->get('sleeping_owl.middleware'))
    ->name('admin.model.mass-sync');

Route::post('{adminModel}/mass-sync', [FormSyncController::class, 'massSycnableHandler'])
    ->middleware(app()['config']->get('sleeping_owl.middleware'))
    ->name('admin.model.mass-sync');
