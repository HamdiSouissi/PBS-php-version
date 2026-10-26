<?php

declare(strict_types=1);

namespace Modules\Authentication\Controllers;

use App\Core\Http\Controller;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Pagination\Paginator;
use Modules\Authentication\Services\UserService;

/**
 * Administration des utilisateurs et profil connecté.
 */
final class UserController extends Controller
{
    public function __construct(private readonly UserService $userService)
    {
    }

    public function index(Request $request): Response
    {
        $this->auth()->requirePermission('users.read');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = (int) config('app.pagination.per_page');
        $repository = \App\Core\Application::container()->get(\Modules\Authentication\Repositories\UserRepository::class);
        $items = $repository->paginateUsers($page, $perPage);
        $total = $repository->countAll();
        $paginator = new Paginator($items, $total, $page, $perPage);

        return $this->view('Authentication::users/index', [
            'title' => 'Utilisateurs',
            'paginator' => $paginator,
            'roles' => $this->userService->allRoles(),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->auth()->requirePermission('users.write');
        $data = $this->validator()->validate($request->all(), [
            'email' => 'required|email|max:180',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'password' => 'required|min:12',
            'is_active' => 'nullable|boolean',
        ]);
        $roleIds = array_map('intval', (array) $request->input('roles', []));
        $this->userService->createUser(
            [...$data, 'is_active' => (bool) $request->input('is_active', true)],
            $roleIds === [] ? [0] : $roleIds
        );
        flash('success', 'Utilisateur créé.');
        return new RedirectResponse('/settings/users');
    }

    public function update(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('users.write');
        $data = $this->validator()->validate($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);
        $roleIds = array_map('intval', (array) $request->input('roles', []));
        $this->userService->updateUser($id, [...$data, 'is_active' => (bool) $request->input('is_active', true)], $roleIds);
        flash('success', 'Utilisateur mis à jour.');
        return new RedirectResponse('/settings/users');
    }

    public function profile(Request $request): Response
    {
        return $this->view('Authentication::users/profile', [
            'title' => 'Mon profil',
            'user' => $this->auth()->user(),
        ]);
    }

    public function updateProfile(Request $request): Response
    {
        $user = $this->auth()->user();
        $data = $this->validator()->validate($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
        ]);
        $this->userService->updateUser($user->id, $data, []);
        flash('success', 'Profil mis à jour.');
        return new RedirectResponse('/profile');
    }

    public function updatePassword(Request $request): Response
    {
        $user = $this->auth()->user();
        $this->userService->changePassword(
            $user->id,
            (string) $request->input('current_password', ''),
            (string) $request->input('password', ''),
            (string) $request->input('password_confirmation', '')
        );
        flash('success', 'Mot de passe modifié.');
        return new RedirectResponse('/profile');
    }

    public function beginTotp(Request $request): Response
    {
        $enrollment = $this->userService->beginTotpEnrollment($this->auth()->user());
        return json_response($enrollment);
    }

    public function confirmTotp(Request $request): Response
    {
        $ok = $this->userService->confirmTotpEnrollment(
            $this->auth()->user(),
            (string) $request->input('code', '')
        );
        if (!$ok) {
            return json_response(['success' => false, 'message' => 'Code invalide.'], 422);
        }
        return json_response(['success' => true]);
    }

    public function disableTotp(Request $request): Response
    {
        $this->auth()->requirePermission('users.write');
        $id = (int) $request->input('user_id', $this->auth()->id());
        $user = \App\Core\Application::container()
            ->get(\Modules\Authentication\Repositories\UserRepository::class)
            ->findWithRights($id);
        if ($user !== null) {
            $this->userService->disableTotp($user);
        }
        flash('success', 'Double authentification désactivée.');
        return new RedirectResponse($request->header('referer') ?? '/settings/users');
    }
}
