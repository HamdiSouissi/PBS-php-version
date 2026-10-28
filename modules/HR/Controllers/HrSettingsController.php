<?php

declare(strict_types=1);

namespace Modules\HR\Controllers;

use App\Core\Http\Controller;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use Modules\HR\Services\LeaveSettingsService;

/**
 * Paramètres RH (acquisition des congés). L'accès est limité au
 * super-administrateur (permission générique '*') et au rôle RH
 * (permission 'hr.settings.manage') par le middleware de route.
 */
final class HrSettingsController extends Controller
{
    public function __construct(private readonly LeaveSettingsService $settings)
    {
    }

    public function index(Request $request): Response
    {
        $this->auth()->requirePermission('hr.settings.manage');
        $monthly = $this->settings->monthlyAccrual();
        return $this->view('HR::settings/index', [
            'title' => 'Paramètres RH',
            'monthlyAccrual' => $monthly,
            'annualEquivalent' => $this->settings->annualEquivalent(),
            'defaultAccrual' => LeaveSettingsService::DEFAULT_MONTHLY_ACCRUAL,
            'minAccrual' => LeaveSettingsService::MIN_MONTHLY_ACCRUAL,
            'maxAccrual' => LeaveSettingsService::MAX_MONTHLY_ACCRUAL,
        ]);
    }

    public function update(Request $request): Response
    {
        $this->auth()->requirePermission('hr.settings.manage');
        $data = $this->validator()->validate($request->all(), [
            'leave_monthly_accrual' => 'required|decimal:4',
        ], ['leave_monthly_accrual' => "nombre de jours acquis par mois"]);

        $this->settings->setMonthlyAccrual((float) $data['leave_monthly_accrual']);
        audit('hr.settings.update', 'Settings', 0, [
            'leave_monthly_accrual' => $data['leave_monthly_accrual'],
        ]);
        flash('success', 'Le rythme d\'acquisition des congés a été enregistré.');
        return new RedirectResponse('/hr/settings');
    }

    public function reset(Request $request): Response
    {
        $this->auth()->requirePermission('hr.settings.manage');
        $this->settings->setMonthlyAccrual(LeaveSettingsService::DEFAULT_MONTHLY_ACCRUAL);
        audit('hr.settings.reset', 'Settings', 0);
        flash('info', 'Valeur par défaut française rétablie (25 jours ouvrés par an).');
        return new RedirectResponse('/hr/settings');
    }
}
