<?php

return [
    'hosts' => explode(',', env('ELASTIC_ORM_HOSTS', '')),
    'username' => env('ELASTIC_ORM_USERNAME', ''),
    'password' => env('ELASTIC_ORM_PASSWORD', ''),
];