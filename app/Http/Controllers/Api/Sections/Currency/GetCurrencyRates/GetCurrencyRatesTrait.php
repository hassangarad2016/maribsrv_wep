<?php

namespace App\Http\Controllers\Api\Sections\Currency\GetCurrencyRates;

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

trait GetCurrencyRatesTrait
{
    public function getCurrencyRates(Request $request) {
        try {
            $validator = Validator::make($request->all(), [
                'currency_name' => 'nullable|string',
                'governorate_code' => 'nullable|string|exists:governorates,code',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }


            $requestedGovernorateCode = $request->input('governorate_code');

            $preferenceData = [
                'favorite_governorate_code' => null,
                'currency_watchlist' => [],
                'metal_watchlist' => [],
                'notification_frequency' => NotificationFrequency::DAILY->value,
            ];

            $currencyWatchlist = collect();
            $metalWatchlist = collect();

            if (Auth::check()) {
                $user = Auth::user();

                /** @var UserPreference|null $preference */
                $preference = $user->preference()->with('favoriteGovernorate')->first();

                if (!$preference) {
                    $preference = $user->preference()->create([
                        'currency_watchlist' => [],
                        'metal_watchlist' => [],
                        'notification_frequency' => NotificationFrequency::DAILY->value,
                    ])->fresh(['favoriteGovernorate']);
                }

                $preferenceResource = new UserPreferenceResource($preference);
                $preferenceData = $preferenceResource->resolve($request);

                if (!$requestedGovernorateCode && !empty($preferenceData['favorite_governorate_code'])) {
                    $requestedGovernorateCode = $preferenceData['favorite_governorate_code'];
                }

                $currencyWatchlist = collect($preferenceData['currency_watchlist'] ?? [])
                    ->map(static fn ($id) => (int) $id)
                    ->filter(static fn ($id) => $id > 0)
                    ->values();

                $metalWatchlist = collect($preferenceData['metal_watchlist'] ?? [])
                    ->map(static fn ($id) => (int) $id)
                    ->filter(static fn ($id) => $id > 0)
                    ->values();
            }


            $requestedGovernorate = null;
            if (!empty($requestedGovernorateCode)) {
                $requestedGovernorate = Governorate::where('code', $requestedGovernorateCode)->first();

                if ($requestedGovernorate && !$requestedGovernorate->is_active) {
                    $requestedGovernorate = null;
                }
            }

            $governorates = Governorate::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            $query = CurrencyRate::with(['quotes.governorate']);

            if ($request->filled('currency_name')) {


                $query->where('currency_name', $request->currency_name);
            }

            $currencies = $query->get();

            $rates = [];
            $anyFallback = false;
            $appliedGovernorate = null;

            foreach ($currencies as $currency) {
                [$quote, $governorate, $usedFallback] = $currency->resolveQuoteForGovernorate($requestedGovernorate);

                if ($governorate && !$appliedGovernorate) {
                    $appliedGovernorate = $governorate;
                }

                $anyFallback = $anyFallback || $usedFallback;

                $rates[] = [
                    'id' => $currency->id,
                    'currency_name' => $currency->currency_name,
                    'sell_price' => $quote?->sell_price,
                    'buy_price' => $quote?->buy_price,
                    'icon_url' => $currency->icon_url,
                    'icon_alt' => $currency->icon_alt,
                    'last_updated_at' => optional($quote?->quoted_at ?? $currency->last_updated_at)->toIso8601String(),
                    'quote_governorate_code' => $governorate?->code,
                    'quote_governorate_name' => $governorate?->name,
                    'quote_source' => $quote?->source,
                    'quote_quoted_at' => optional($quote?->quoted_at)->toIso8601String(),
                    'quote_is_default' => (bool) ($quote?->is_default ?? false),
                    'quote_used_fallback' => $usedFallback,
                    'is_watchlisted' => $currencyWatchlist->contains((int) $currency->id),


                ];
            }

            $requestedGovernorateData = $requestedGovernorate ? [
                'code' => $requestedGovernorate->code,
                'name' => $requestedGovernorate->name,
            ] : null;

            $appliedGovernorateData = $appliedGovernorate ? [
                'code' => $appliedGovernorate->code,
                'name' => $appliedGovernorate->name,
            ] : null;
            $notificationOptions = collect(NotificationFrequency::cases())
                ->map(static fn (NotificationFrequency $frequency) => [
                    'value' => $frequency->value,
                    'label' => $frequency->label(),
                ])->values();

            ResponseService::successResponse(
                "Currency rates fetched successfully.",
                $rates,
                [
                    'governorates' => $governorates->map(fn (Governorate $governorate) => [
                        'code' => $governorate->code,
                        'name' => $governorate->name,
                    ])->values(),
                    'requested_governorate' => $requestedGovernorateData,
                    'applied_governorate' => $appliedGovernorateData,
                    'used_fallback' => $anyFallback,
                    'requested_governorate_code' => $requestedGovernorate?->code ?? $requestedGovernorateCode,
                    'preferences' => $preferenceData,
                    'preference_options' => [
                        'notification_frequencies' => $notificationOptions,
                    ],
                ]
            );


        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getCurrencyRates");
            ResponseService::errorResponse();
        }
    }
}