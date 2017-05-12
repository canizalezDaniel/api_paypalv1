<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/
/* Metodo que ejecuta el pago en Paypal POST*/

Route::post('payment', array(
    'as' => 'payment',
    'uses' => 'PaymentController@postPayment',
));
/* Metodo que devuelve el estatus de pago Paypal GET*/

Route::get('payment/status', array(
    'as' => 'payment.status',
    'uses' => 'PaymentController@getPaymentStatus',
));

