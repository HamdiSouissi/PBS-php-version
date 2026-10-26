<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Services\DashboardService;

final class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard)
    {
    }

    public function index(Request $request): Response
    {
        return $this->view('dashboard', [
            'title' => 'Tableau de bord',
            'kpis' => $this->dashboard->kpis(),
            'recentInvoices' => $this->dashboard->recentInvoices(),
            'obligations' => $this->dashboard->upcomingObligations(),
            'revenueSeries' => $this->dashboard->revenueSeries(),
        ]);
    }
}
