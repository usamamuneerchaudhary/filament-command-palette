<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;

arch('the package uses strict types')
    ->expect('Usamamuneerchaudhary\\CommandPalette')
    ->toUseStrictTypes()
    ->ignoring('Usamamuneerchaudhary\\CommandPalette\\Tests');

arch('support classes do not reach for unmockable request facades')
    ->expect('Usamamuneerchaudhary\\CommandPalette\\Support')
    ->not->toUse([
        Auth::class,
        Request::class,
        Session::class,
    ]);
