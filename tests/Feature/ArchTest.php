<?php

// Pruebas de arquitectura: guardrails que se verifican en cada corrida de la
// suite para mantener convenciones y evitar regresiones estructurales.

arch('no quedan helpers de depuración')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'var_export', 'print_r'])
    ->not->toBeUsed();

arch('el dominio no depende de controladores ni de Inertia')
    ->expect('App\Domain')
    ->not->toUse(['App\Http\Controllers', 'Inertia\Inertia']);

arch('los modelos extienden Eloquent')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model')
    ->ignoring([
        'App\Models\User',
        'App\Models\Customer',
        'App\Models\Mediable',
        'App\Models\Concerns\HasMedia',
    ]);

arch('los controladores extienden el controlador base')
    ->expect('App\Http\Controllers')
    ->toExtend('App\Http\Controllers\Controller')
    ->ignoring('App\Http\Controllers\Controller');

arch('los form requests extienden FormRequest')
    ->expect('App\Http\Requests')
    ->toExtend('Illuminate\Foundation\Http\FormRequest');

arch('los enums de estado viven en su sitio')
    ->expect('App\Domain\Payment\PaymentStatus')
    ->toBeEnum();
