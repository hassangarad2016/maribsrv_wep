<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreOnboardingRequest;
use App\Http\Resources\StoreResource;
use App\Models\PendingSignup;
use App\Models\User;
use App\Models\WalletAccount;
use App\Services\Store\StoreRegistrationService;
use App\Services\Store\StoreNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class StoreOnboardingController extends Controller
{
    public function __construct(
        private readonly StoreRegistrationService $storeRegistrationService,
        private readonly StoreNotificationService $storeNotificationService
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $store = $request->user()
            ->stores()
            ->with(['settings', 'workingHours', 'policies', 'staff'])
            ->latest()
            ->first();

        if (! $store) {
            return response()->json([
                'data' => null,
            ]);
        }

        return response()->json([
            'data' => new StoreResource($store),
        ]);
    }

    public function store(StoreOnboardingRequest $request): JsonResponse
    {
        $logPayload = $request->sanitizePayloadForLog($request->all());

        \Log::info('store_onboarding.request', [
            'user_id' => $request->user()?->id,
            'payload' => $logPayload,
        ]);

        try {
            [$store, $resolvedUser, $issuedToken] = DB::transaction(function () use ($request) {
                $resolvedUser = $request->user();
                $issuedToken = null;

                if (! $resolvedUser) {
                    [$resolvedUser, $issuedToken] = $this->finalizePendingSignup($request);
                }

                if (! $resolvedUser) {
                    throw new RuntimeException('Unauthenticated.', HttpResponse::HTTP_UNAUTHORIZED);
                }

                $store = $this->storeRegistrationService->register(
                    $resolvedUser,
                    $request->validated()
                );

                return [$store, $resolvedUser, $issuedToken];
            });

            try {
                $this->storeNotificationService->notifyStoreSubmitted($store);
            } catch (Throwable $throwable) {
                \Log::warning('store_onboarding.notification_failed', [
                    'store_id' => $store->getKey(),
                    'user_id' => $resolvedUser?->getKey(),
                    'message' => $throwable->getMessage(),
                ]);
            }

            $response = [
                'message' => __('تم حفظ بيانات المتجر بنجاح.'),
                'data' => new StoreResource($store),
            ];

            if ($issuedToken) {
                $response['token'] = $issuedToken;
                $response['user'] = $resolvedUser;
            }

            return response()->json($response, 201);
        } catch (RuntimeException $exception) {
            $status = $exception->getCode();
            if ($status < 400) {
                $status = HttpResponse::HTTP_UNPROCESSABLE_ENTITY;
            }

            return response()->json([
                'message' => __($exception->getMessage()),
            ], $status);
        } catch (ValidationException $exception) {
            $failedPayload = $request->sanitizePayloadForLog($request->all());

            \Log::warning('store_onboarding.validation_failed', [
                'user_id' => $request->user()?->id,
                'errors' => $exception->errors(),
                'payload' => $failedPayload,
            ]);

            throw $exception;
        } catch (Throwable $throwable) {
            \Log::error('store_onboarding.failed', [
                'user_id' => $request->user()?->id,
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
                'payload' => $request->sanitizePayloadForLog($request->all()),
            ]);

            return response()->json([
                'message' => __('تعذر حفظ بيانات المتجر، يرجى المحاولة مرة أخرى.'),
            ], 500);
        }
    }
    /**
     * @return array{0: User, 1: string|null}
     */
    private function finalizePendingSignup(StoreOnboardingRequest $request): array
    {
        $pendingId = $request->input('pending_signup_id');
        $pendingToken = $request->input('pending_signup_token');

        if (empty($pendingId) || empty($pendingToken)) {
            throw new RuntimeException('Unauthenticated.', HttpResponse::HTTP_UNAUTHORIZED);
        }

        $pendingSignup = PendingSignup::whereKey($pendingId)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->first();

        if (! $pendingSignup) {
            throw new RuntimeException('Pending signup not found.', HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $payload = $pendingSignup->payloadAsArray();
        $completionToken = $payload['completion_token'] ?? null;

        if (! is_string($completionToken) ||
            $completionToken === '' ||
            ! hash_equals($completionToken, (string) $pendingToken)) {
            throw new RuntimeException('Invalid pending signup token.', HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $userData = $payload['user'] ?? null;
        if (! is_array($userData) || $userData === []) {
            $pendingSignup->delete();
            throw new RuntimeException('Pending signup data is invalid.', HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $accountType = (int) ($userData['account_type'] ?? 0);
        if ($accountType !== User::ACCOUNT_TYPE_SELLER) {
            throw new RuntimeException('Pending signup is not a commercial account.', HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        unset($userData['normalized_mobile']);
        $userData['phone_verified_at'] = now();

        $user = User::create($userData);
        $walletCurrency = strtoupper((string) config('wallet.currency', config('app.currency', 'SAR')));
        WalletAccount::firstOrCreate(
            ['user_id' => $user->getKey()],
            ['balance' => 0, 'currency' => $walletCurrency]
        );

        if (! $user->hasRole('User')) {
            $user->assignRole('User');
        }

        Auth::guard('web')->login($user);
        $auth = User::find($user->id) ?? $user;

        $pendingSignup->delete();

        $token = $auth->createToken($auth->name ?? '')->plainTextToken;

        return [$auth, $token];
    }
}
