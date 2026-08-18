<x-mail::message>
<div style="background-image: url('{{ $message->embed(public_path('images/BG_Kominfo.png')) }}'); background-size: cover; background-position: center; padding: 40px 20px; text-align: center; border-radius: 8px; margin-bottom: 25px; border: 1px solid #e2e8f0;">
    <img src="{{ $message->embed(public_path('images/Icon.png')) }}" alt="Icon Kominfo" style="height: 80px; width: auto; background-color: rgba(255, 255, 255, 0.95); padding: 12px; border-radius: 50%; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
</div>

{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# @lang('Oops, Terjadi Kesalahan!')
@else
# @lang('Halo!')
@endif
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Action Button --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else
@lang('Salam Hormat,')<br>
**Tim {{ config('app.name') }}**
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
@lang(
    "Jika Anda kesulitan menekan tombol \":actionText\", salin dan tempel URL di bawah ini\n".
    'ke dalam web browser Anda:',
    [
        'actionText' => $actionText,
    ]
) <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
