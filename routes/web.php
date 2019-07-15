<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->get('hello_world', 'MTDController@helloWorld');
$router->get('hello_application', 'MTDController@helloApplication');
$router->get('new_user', 'MTDController@newUser');

$router->get('callback', 'MTDController@callback');
$router->post('obligations', 'MTDController@obligations');
$router->post('view_return', 'MTDController@viewReturn');
$router->post('liabilities', 'MTDController@liabilities');
$router->post('payments', 'MTDController@payments');
$router->post('submit_return', 'MTDController@submitReturn');