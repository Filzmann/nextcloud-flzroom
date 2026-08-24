<?php

declare(strict_types=1);

return ['routes' => [
    ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
    ['name' => 'api#month', 'url' => '/api/month/{month}', 'verb' => 'GET'],
    ['name' => 'api#createBooking', 'url' => '/api/bookings', 'verb' => 'POST'],
    ['name' => 'api#updateBooking', 'url' => '/api/bookings/{id}', 'verb' => 'PUT'],
    ['name' => 'api#deleteBooking', 'url' => '/api/bookings/{id}', 'verb' => 'DELETE'],
    ['name' => 'api#createRoom', 'url' => '/api/rooms', 'verb' => 'POST'],
    ['name' => 'api#updateRoom', 'url' => '/api/rooms/{id}', 'verb' => 'PUT'],
    ['name' => 'api#deleteRoom', 'url' => '/api/rooms/{id}', 'verb' => 'DELETE'],
    ['name' => 'demo_admin#install', 'url' => '/api/admin/demo-pack/install', 'verb' => 'POST'],
    ['name' => 'retention_admin#settings', 'url' => '/api/admin/settings', 'verb' => 'GET'],
    ['name' => 'retention_admin#savePolicy', 'url' => '/api/admin/retention-policy', 'verb' => 'PUT'],
    ['name' => 'retention_admin#saveLayout', 'url' => '/api/admin/layout', 'verb' => 'PUT'],
]];
