<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth\AuthManager;
use App\Core\Http\Controller;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;

final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $auth = container()->get(AuthManager::class);
        return new RedirectResponse($auth->check() ? '/dashboard' : '/login');
    }
}
