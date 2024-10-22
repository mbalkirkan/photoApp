<?php

use App\Http\Controllers\PhotoController;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

Route::get('/', [\App\Http\Controllers\PhotoController::class, 'get']);
Route::post('/', [\App\Http\Controllers\PhotoController::class, 'upload']);

Route::get('/photos/last-merged', [PhotoController::class, 'getMergedPhoto']);

Route::get('/photo/merged', [PhotoController::class, 'showMergedPhoto']);

Route::get('/test-mail', function () {
    $details = [
        'subject' => 'Laravel Mail Test',
        'body' => 'Bu, Laravel ile gönderilen test e-postasıdır.'
    ];

    Mail::raw($details['body'], function ($message) use ($details) {
        $message->to('ekrembey435@gmail.com')
            ->subject($details['subject']);
    });

    return 'Mail gönderildi!';
});
