<?php

use App\Providers\AppServiceProvider;
use Mews\Captcha\CaptchaServiceProvider;
use Tymon\JWTAuth\Providers\LaravelServiceProvider;

return [
    AppServiceProvider::class,
    LaravelServiceProvider::class,
    CaptchaServiceProvider::class,
];
