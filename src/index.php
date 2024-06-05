<?php
require_once __DIR__ . '/vendor/autoload.php';

use Gimli\Router\Route;

$route = Route::getInstance();

$route->get('/a', function() {
	return 'Hello, World!';
});

Route::get('/', function() {
	return 'Hello, World!';
});

Route::post('/about', ['\Controllers\About', 'index']);

Route::group('/api', function() {
	Route::get('/users', function() {
		return 'Users';
	});
	Route::get('/posts', function() {
		return 'Posts';
	});
	Route::post('/posts', function() {
		return 'Create Post';
	});

	Route::group('/v1', function() {
		Route::get('/users', function() {
			return 'Users v1';
		});
		Route::get('/posts', function() {
			return 'Posts v1';
		});
		Route::post('/posts', function() {
			return 'Create Post v1';
		});
	});

	Route::group('/v2', function() {
		Route::get('/users', function() {
			return 'Users v2';
		});
		Route::get('/posts', function() {
			return 'Posts v2';
		});
		Route::post('/posts', function() {
			return 'Create Post v2';
		});
	});
});

var_dump(Route::build());