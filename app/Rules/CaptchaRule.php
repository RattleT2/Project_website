<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CaptchaRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (config('captcha.disable')) {
            return;
        }

        $captchaKey = request()->input('captcha_key');

        if (!$captchaKey) {
            $fail('CAPTCHA key tidak ditemukan. Silakan muat ulang captcha.');
            return;
        }

        if (!captcha_api_check($value, $captchaKey)) {
            $fail('CAPTCHA tidak valid. Silakan coba lagi.');
        }
    }
}
