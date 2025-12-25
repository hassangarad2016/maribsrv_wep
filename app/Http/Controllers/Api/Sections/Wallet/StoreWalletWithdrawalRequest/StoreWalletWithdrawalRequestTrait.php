<?php

namespace App\Http\Controllers\Api\Sections\Wallet\StoreWalletWithdrawalRequest;

use App\Events\ManualPaymentRequestCreated;
use App\Events\MessageDelivered;
use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\UserPresenceUpdated;
use App\Events\UserTyping;
use App\Http\Resources\ItemCollection;
use App\Http\Resources\ManualPaymentRequestResource;
use App\Http\Resources\PaymentTransactionResource;
use App\Http\Resources\WalletTransactionResource;
use App\Http\Resources\SliderResource;
use App\Services\SliderMetricService;
use App\Models\ManualPaymentRequestHistory;
use App\Services\DepartmentAdvertiserService;
use App\Services\TelemetryService;
use App\Models\Area;
use App\Models\BlockUser;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\City;
use App\Models\ContactUs;
use App\Models\Country;
use App\Models\CustomField;
use App\Models\Faq;
use App\Models\Favourite;
use App\Models\FeaturedAdsConfig;
use App\Models\FeaturedItems;
use App\Models\Item;
use App\Models\ItemCustomFieldValue;
use App\Models\ItemImages;
use App\Models\ItemOffer;
use App\Models\Language;
use App\Models\Notifications;
use App\Models\Package;
use App\Models\ManualBank;
use App\Models\ManualPaymentRequest;
use App\Models\PaymentConfiguration;
use App\Models\PaymentTransaction;
use App\Models\ReportReason;
use App\Models\SellerRating;
use App\Models\SeoSetting;
use App\Models\Service;
use App\Models\ServiceCustomField;
use App\Models\ServiceCustomFieldValue;
use App\Models\ServiceRequest;
use App\Models\ServiceReview;
use App\Models\Setting;
use Illuminate\Pagination\AbstractPaginator;
use App\Services\DelegateNotificationService;
use App\Models\Slider;
use App\Models\SocialLogin;
use App\Models\State;
use App\Models\Tip;
use App\Models\TipTranslation;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Models\UserPurchasedPackage;
use App\Models\UserReports;
use App\Models\VerificationField;
use App\Models\VerificationFieldRequest;
use App\Models\VerificationFieldValue;
use App\Models\VerificationPlan;
use App\Models\VerificationRequest;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Models\WalletWithdrawalRequest;
use App\Models\ReferralAttempt;
use App\Services\SliderEligibilityService;
use App\Models\ServiceReviewReport;
use App\Policies\SectionDelegatePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\UploadedFile;
use App\Models\CurrencyRateQuote;
use App\Events\CurrencyCreated;
use App\Events\CurrencyRatesUpdated;
use App\Models\Governorate;
use App\Models\CurrencyRate;
use App\Models\Challenge;
use App\Models\Referral;
use App\Models\DepartmentTicket;
use App\Services\DepartmentSupportService;
use App\Enums\NotificationFrequency;
use App\Http\Resources\UserPreferenceResource;
use App\Models\UserPreference;
use App\Models\RequestDevice;
use App\Models\Order;
use App\Services\CachingService;
use App\Services\DelegateAuthorizationService;
use App\Services\DepartmentReportService;
use App\Services\FileService;
use App\Services\InterfaceSectionService;
use App\Services\HelperService;
use App\Services\NotificationService;
use App\Services\PaymentFulfillmentService;
use App\Services\WalletService;
use App\Services\ResponseService;
use App\Services\ServiceAuthorizationService;
use App\Services\Location\MaribBoundaryService;
use App\Services\ReferralAuditLogger;
use DateTimeInterface;
use App\Services\Pricing\ActivePricingPolicyCache;

use App\Models\Pricing\PricingPolicy;
use App\Models\Pricing\PricingDistanceRule;
use App\Models\Pricing\PricingWeightTier;
use App\Models\DeliveryPrice;



use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Resources\WalletWithdrawalRequestResource;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;


use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\OTP;
use App\Models\PendingSignup;
use App\Jobs\SendOtpWhatsAppJob;
use App\Services\EnjazatikWhatsAppService;
use Throwable;
use Exception;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use App\Services\ImageVariantService;

use JsonException;

trait StoreWalletWithdrawalRequestTrait
{
    public function storeWalletWithdrawalRequest(Request $request): void
    {
        $methods = $this->getWalletWithdrawalMethods();

        $minimumAmount = (float) config('wallet.withdrawals.minimum_amount', 0);
        $amountRules = ['required', 'numeric'];
        if ($minimumAmount > 0) {
            $amountRules[] = 'min:' . $minimumAmount;
        }

        $validator = Validator::make($request->all(), [
            'amount' => $amountRules,
            'preferred_method' => ['required', Rule::in(array_keys($methods))],
            'notes' => ['nullable', 'string', 'max:500'],
            'meta' => ['nullable', 'array'],
        ], [], [
            'preferred_method' => __('Preferred method'),
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        $validated = $validator->validated();

        $methodKey = $validated['preferred_method'];
        $method = $methods[$methodKey];
        $withdrawalMeta = $this->validateWithdrawalMeta($request, $method);


        try {
            $user = Auth::user();

            $walletAccount = WalletAccount::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'balance' => 0,
                    'currency' => $this->getWalletCurrencyCode(),
                ]
            );
            $walletAccount = $this->ensureWalletAccountCurrency($walletAccount);
            $walletCurrency = Str::upper((string) ($walletAccount->currency ?? $this->getWalletCurrencyCode()));

            $amount = round((float) $validated['amount'], 2);

            if ($amount > (float) $walletAccount->balance) {
                ResponseService::errorResponse('Insufficient wallet balance');
            }



            $idempotencyKey = sprintf('wallet:withdrawal-request:%d:%s', $user->id, Str::uuid()->toString());

            $transactionMeta = [
                'context' => 'wallet_withdrawal_request',
                'withdrawal_request_reference' => $idempotencyKey,
                'withdrawal_method' => $methodKey,
            ];

            if (!empty($validated['notes'])) {
                $transactionMeta['withdrawal_notes'] = $validated['notes'];
            }

            if ($withdrawalMeta !== null) {
                $transactionMeta['withdrawal_meta'] = $withdrawalMeta;
            }

            $transaction = $this->walletService->debit($user, $idempotencyKey, $amount, [
                'meta' => $transactionMeta,
            ]);

            $withdrawalRequest = WalletWithdrawalRequest::create([
                'wallet_account_id' => $walletAccount->getKey(),
                'wallet_transaction_id' => $transaction->getKey(),
                'status' => WalletWithdrawalRequest::STATUS_PENDING,
                'amount' => $amount,
                'preferred_method' => $methodKey,
                'wallet_reference' => $idempotencyKey,
                'notes' => $validated['notes'] ?? null,
                'meta' => $withdrawalMeta,
            ]);


            $data = [
                'id' => $withdrawalRequest->getKey(),
                'status' => $withdrawalRequest->status,
                'status_label' => $withdrawalRequest->statusLabel(),
                'amount' => (float) $withdrawalRequest->amount,
                'currency' => $walletCurrency,

                'preferred_method' => [
                    'key' => $method['key'],
                    'name' => $method['name'],
                    'description' => $method['description'],
                ],
                'wallet_transaction_id' => $transaction->getKey(),
                'wallet_reference' => $withdrawalRequest->wallet_reference,
                'notes' => $withdrawalRequest->notes,
                'submitted_at' => optional($withdrawalRequest->created_at)->toIso8601String(),
                'balance_after' => (float) $transaction->balance_after,
            ];

            if ($withdrawalRequest->meta !== null) {
                $data['meta'] = $withdrawalRequest->meta;
            }

            ResponseService::successResponse('Wallet withdrawal request submitted successfully', $data);
        } catch (RuntimeException $runtimeException) {
            if (str_contains(strtolower($runtimeException->getMessage()), 'insufficient wallet balance')) {
                ResponseService::errorResponse('Insufficient wallet balance');
            }

            ResponseService::logErrorResponse($runtimeException, 'API Controller -> storeWalletWithdrawalRequest');
            ResponseService::errorResponse('Failed to submit withdrawal request');
        } catch (Throwable $throwable) {
            ResponseService::logErrorResponse($throwable, 'API Controller -> storeWalletWithdrawalRequest');
            ResponseService::errorResponse('Failed to submit withdrawal request');
        }
    }
}
