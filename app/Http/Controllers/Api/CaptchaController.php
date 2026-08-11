<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CaptchaController extends Controller
{
    public function generate(): JsonResponse
    {
        $captcha = app('captcha')->create('default', true);

        return response()->json([
            'captcha_img' => $captcha['img'],
            'captcha_key' => $captcha['key'],
        ]);
    }

    public function reload(): JsonResponse
    {
        $captcha = app('captcha')->create('default', true);

        return response()->json([
            'captcha_img' => $captcha['img'],
            'captcha_key' => $captcha['key'],
        ]);
    }
}
