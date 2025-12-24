<?php

namespace App\Http\Controllers\Api\Sections\ManualPayments\GetManualPaymentRequests;

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

trait GetManualPaymentRequestsTrait
{
    public function getManualPaymentRequests(Request $request) {
        $filters = $this->normalizeManualPaymentRequestFilters($request->all());


        $gatewayAliasMap = $this->manualPaymentGatewayAliasMap();
        $gatewayValidationValues = array_values(array_unique(array_merge(
            array_keys($gatewayAliasMap),
            array_merge(...array_values($gatewayAliasMap))
        )));


        $validator = Validator::make($filters, [
            'status' => ['nullable', Rule::in([
                ManualPaymentRequest::STATUS_PENDING,
                ManualPaymentRequest::STATUS_UNDER_REVIEW,
                ManualPaymentRequest::STATUS_APPROVED,
                ManualPaymentRequest::STATUS_REJECTED,
            ])],
            'payment_gateway' => ['nullable', Rule::in($gatewayValidationValues)],
            'department' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],


        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        $validated = $validator->validated();


        $status = $validated['status'] ?? $filters['status'] ?? null;
        $paymentGateway = $this->normalizeManualPaymentGateway(
            $validated['payment_gateway'] ?? $filters['payment_gateway'] ?? null
        );
        
        $department = array_key_exists('department', $validated)
            ? $validated['department']
            : ($filters['department'] ?? null);

        $page = (int) ($validated['page'] ?? $filters['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? $filters['per_page'] ?? 15);

        if ($perPage < 1) {
            $perPage = 15;
        } elseif ($perPage > 100) {
            $perPage = 100;
        }


        try {
            $query = ManualPaymentRequest::query()
                ->with([
                    'manualBank',
                    'payable',
                    'paymentTransaction.order',
                ]);

            $this->applyManualPaymentRequestVisibilityScope($query, (int) Auth::id());

            $query
                ->when($status, static function ($builder, string $statusValue) {
                    $builder->where('manual_payment_requests.status', $statusValue);


                })
                ->when($paymentGateway, function ($builder, string $gateway) {
                    $aliases = $this->expandManualPaymentGatewayAliases($gateway);

                    $builder->where(static function ($query) use ($aliases, $gateway) {
                        $query->whereHas('paymentTransaction', static function ($transactionQuery) use ($aliases) {
                            $transactionQuery->whereIn('payment_gateway', $aliases);
                        });

                        if (in_array($gateway, ['manual_banks', 'manual_bank'], true)) {
                            $query->orWhereDoesntHave('paymentTransaction');
                        }
                    });
                })
                ->when(
                    $department !== null,
                    static function ($builder) use ($department) {
                        $builder->where(static function ($query) use ($department) {
                            $query->where('manual_payment_requests.department', $department)
                                ->orWhereNull('manual_payment_requests.department');
                        });
                    }
                )
                ->orderByDesc('manual_payment_requests.id');
            $stats = $this->summarizeManualPaymentRequests($query);

            $paginator = $query->paginate($perPage, ['manual_payment_requests.*'], 'page', $page);

            $requests = ManualPaymentRequestResource::collection(collect($paginator->items()))->resolve();

            $meta = [
                'total' => (int) $paginator->total(),
                'current_page' => (int) $paginator->currentPage(),
                'last_page' => (int) max($paginator->lastPage(), 1),
                'per_page' => (int) $paginator->perPage(),
            ];

            ResponseService::successResponse(
                'â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–“â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط¨ â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â”¤â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–‘ â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ”œطھط¸أ©ط´ط¸آ€آ£â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–‘â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–“ â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬طھâ•ھطھâ”¬â•—â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ•ھâ••â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھطھâ”¬ط« â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط°â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬طھâ•ھطھâ”¬â•—â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط° â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–‘â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´â”¬ط¨â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â”¤â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â•،',
                [
                    'manual_payment_requests' => $requests,
                    'items' => $requests,
                    'meta' => $meta,
                    'stats' => $stats,

                ]
            );
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getManualPaymentRequests');
            ResponseService::errorResponse();
        }
    }
}