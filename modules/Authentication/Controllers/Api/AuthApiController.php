<?php

declare(strict_types=1);

namespace Modules\Authentication\Controllers\Api;

use App\Core\Auth\AuthManager;
use App\Core\Http\Controller;
use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Core\Security\Jwt;
use App\Core\Security\RateLimiter;
use Modules\Authentication\Services\AuthService;

/**
 * Authentification REST /api/v1/auth : jetons JWT courts + refresh.
 */
final class AuthApiController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly Jwt $jwt,
        private readonly AuthManager $auth,
        private readonly RateLimiter $rateLimiter
    ) {
    }

    public function login(Request $request): JsonResponse
    {
        $data = $this->validator()->validate($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);
        if (!$this->rateLimiter->attempt('api-login:' . $request->ip(), 5, 300)) {
            return json_response(['success' => false, 'error' => 'Trop de tentatives.'], 429);
        }

        try {
            $user = $this->authService->attempt((string) $data['email'], (string) $data['password'], $request->ip());
        } catch (\Throwable $exception) {
            return json_response(['success' => false, 'error' => $exception->getMessage()], 401);
        }
        if ($user->totpEnrolled) {
            return json_response([
                'success' => true,
                'two_factor_required' => true,
                'message' => 'Un code TOTP est requis sur /auth/2fa.',
            ], 200);
        }
        return json_response($this->issueTokens($user));
    }

    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $data = $this->validator()->validate($request->all(), [
            'email' => 'required|email',
            'code' => 'required|string',
        ]);
        $repository = \App\Core\Application::container()
            ->get(\Modules\Authentication\Repositories\UserRepository::class);
        $user = $repository->findByEmail((string) $data['email']);
        if ($user === null || !$this->authService->validateTwoFactor($user, (string) $data['code'])) {
            return json_response(['success' => false, 'error' => 'Code TOTP invalide.'], 401);
        }
        return json_response($this->issueTokens($user));
    }

    public function refresh(Request $request): JsonResponse
    {
        try {
            $claims = $this->jwt->verify((string) $request->input('refresh_token', ''));
        } catch (\Throwable $exception) {
            return json_response(['success' => false, 'error' => $exception->getMessage()], 401);
        }
        if (($claims['typ'] ?? '') !== 'refresh') {
            return json_response(['success' => false, 'error' => 'Jeton de rafraîchissement attendu.'], 401);
        }
        $repository = \App\Core\Application::container()
            ->get(\Modules\Authentication\Repositories\UserRepository::class);
        $user = $repository->findWithRights((int) $claims['sub']);
        if ($user === null || !$user->isActive) {
            return json_response(['success' => false, 'error' => 'Utilisateur invalide.'], 401);
        }
        return json_response($this->issueTokens($user));
    }

    public function me(Request $request): JsonResponse
    {
        return json_response(['success' => true, 'data' => $this->auth->user()?->toArray()]);
    }

    /**
     * @return array<string,mixed>
     */
    private function issueTokens(\Modules\Authentication\Models\User $user): array
    {
        $access = $this->jwt->issue([
            'sub' => $user->id,
            'email' => $user->email,
            'typ' => 'access',
        ]);
        $refresh = $this->jwt->issue([
            'sub' => $user->id,
            'typ' => 'refresh',
        ], (int) config('security.jwt.refresh_ttl'));
        return [
            'success' => true,
            'token_type' => 'Bearer',
            'access_token' => $access,
            'refresh_token' => $refresh,
            'expires_in' => (int) config('security.jwt.ttl'),
            'user' => $user->toArray(),
        ];
    }
}
