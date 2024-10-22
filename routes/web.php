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
        'subject' => 'Your Photo from Laneige The Grove Pop-up!',
        'body' => '
            <html>
            <head>
            <title>Your Photo from Laneige The Grove Pop-up!</title>
            </head>
            <body>
            <p>Thanks for visiting our Laneige The Grove Pop-up!

            Enclosed please find your image from our photo booth! Don\'t forget to tag and follow @laneige_us !

            Hope you revisit us again soon!</p>
            <img src="' . url('/photo/merged?id=16') . '" alt="Your Photo" />
            </body>
            </html>
            '
    ];

    Mail::html($details['body'], function ($message) use ($details) {
        $message->to('ekrembey435@gmail.com')
            ->subject($details['subject']);
    });

    return 'Mail gönderildi!';
});
