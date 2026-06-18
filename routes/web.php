<?php

use Illuminate\Support\Facades\Route;
use Vimatech\Invitation\Http\Controllers\InvitationController;

$middleware = config('invitation.routes.middleware', ['web']);
$throttle = config('invitation.routes.throttle', 'throttle:30,1');

if ($throttle) {
    $middleware[] = $throttle;
}

Route::middleware($middleware)
    ->prefix(config('invitation.routes.prefix', 'invitations'))
    ->group(function () {
        Route::get('/{token}', [InvitationController::class, 'preview'])
            ->where('token', '[a-zA-Z0-9]{64}')
            ->name('invitations.preview');

        Route::post('/{token}/accept', [InvitationController::class, 'accept'])
            ->where('token', '[a-zA-Z0-9]{64}')
            ->name('invitations.accept');

        Route::post('/{token}/decline', [InvitationController::class, 'decline'])
            ->where('token', '[a-zA-Z0-9]{64}')
            ->name('invitations.decline');
    });
