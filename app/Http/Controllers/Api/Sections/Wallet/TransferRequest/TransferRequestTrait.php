<?php

namespace App\Http\Controllers\Api\Sections\Wallet\TransferRequest;

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

trait TransferRequestTrait
{
    public function transferRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'client_tag' => ['required', 'string', 'max:64'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'currency' => ['nullable', 'string', 'max:16'],
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        $validated = $validator->validated();

        try {
            $sender = Auth::user();
            $recipient = User::query()->findOrFail($validated['recipient_id']);

            if ($sender->id === $recipient->id) {
                ResponseService::validationError('Cannot transfer funds to the same account.');
            }

            $amount = (float) $validated['amount'];
            $clientTag = $validated['client_tag'];
            $reference = $validated['reference'] ?? null;
            $notes = $validated['notes'] ?? null;
            
            $currencyInput = $validated['currency'] ?? null;
            $walletCurrency = $this->getWalletCurrencyCode();

            if ($currencyInput !== null) {
                $normalizedCurrency = $this->normalizeCurrencyCode($currencyInput);

                if ($normalizedCurrency === null) {
                    ResponseService::validationError('Invalid currency provided.');
                }

                if ($normalizedCurrency !== $walletCurrency) {
                    ResponseService::validationError(sprintf(
                        'Wallet transfers must use the %s currency.',
                        $walletCurrency
                    ));
                }
            }



            $idempotencyKey = $this->buildWalletTransferIdempotencyKey($sender, $recipient, $amount, $clientTag);

            [$debitTransaction, $creditTransaction, $replayed] = $this->performWalletTransfer(
                $sender,
                $recipient,
                $amount,
                $idempotencyKey,
                $clientTag,
                $walletCurrency,
                $reference,
                $notes
            );

            $responseCurrency = $walletCurrency;


            $data = [
                'idempotency_key' => $idempotencyKey,
                'amount' => round($amount, 2),
                'currency' => Str::upper((string) $responseCurrency),
                'sender' => [
                    'id' => $sender->id,
                    'name' => $sender->name,
                    'transaction_id' => $debitTransaction->getKey(),
                    'balance_after' => (float) $debitTransaction->balance_after,
                ],
                'recipient' => [
                    'id' => $recipient->id,
                    'name' => $recipient->name,
                    'transaction_id' => $creditTransaction->getKey(),
                    'balance_after' => (float) $creditTransaction->balance_after,
                ],
                'meta' => array_filter([
                    'reference' => $reference,
                    'notes' => $notes,
                    'client_tag' => $clientTag,
                ], static fn ($value) => $value !== null && $value !== ''),
                'processed_at' => optional($debitTransaction->created_at)->toIso8601String(),
                'idempotency_replayed' => $replayed,
            ];

            ResponseService::successResponse('Wallet transfer processed successfully', $data);
        } catch (RuntimeException $runtimeException) {
            if (str_contains(strtolower($runtimeException->getMessage()), 'insufficient wallet balance')) {
                ResponseService::errorResponse('Insufficient wallet balance');
            }

            ResponseService::logErrorResponse($runtimeException, 'API Controller -> transferRequest');
            ResponseService::errorResponse('Failed to process wallet transfer');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> transferRequest');
            ResponseService::errorResponse();
        }
    }
}