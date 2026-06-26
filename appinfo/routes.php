<?php

declare(strict_types=1);

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'download#index', 'url' => '/api/downloads', 'verb' => 'GET'],
		['name' => 'download#create', 'url' => '/api/downloads', 'verb' => 'POST'],
		['name' => 'download#clearHistory', 'url' => '/api/downloads/history', 'verb' => 'DELETE'],
		['name' => 'download#destroy', 'url' => '/api/downloads/{id}', 'verb' => 'DELETE'],
		['name' => 'template#index', 'url' => '/api/templates', 'verb' => 'GET'],
		['name' => 'template#create', 'url' => '/api/templates', 'verb' => 'POST'],
		['name' => 'template#destroy', 'url' => '/api/templates/{id}', 'verb' => 'DELETE'],
	],
];
