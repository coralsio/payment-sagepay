<?php

use Illuminate\Support\Facades\Route;


Route::group(['prefix' => 'sagepay'], function () {
    Route::match(['get', 'post'], '/webhooks/client-redirect',
        'SagePayController@clientRedirect')->name('ThreeDSNotificationURL');
});
