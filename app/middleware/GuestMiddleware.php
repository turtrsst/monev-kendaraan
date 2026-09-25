<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class GuestMiddleware
{
    public static function handle(Request $request): void
    {
        if (Session::user() !== null) {
            Response::redirect('/beranda');
        }
    }
}
