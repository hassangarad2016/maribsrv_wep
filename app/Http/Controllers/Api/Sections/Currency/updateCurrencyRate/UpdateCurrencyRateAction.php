<?php

namespace App\Http\Controllers\Api\Sections\Currency\updateCurrencyRate;

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


trait UpdateCurrencyRateAction
{
    public function updateCurrencyRate(Request $request) {
            try {
                $validator = Validator::make($request->all(), [
                    'currency_name' => 'required|string',
                    'sell_price' => 'required|numeric',
                    'buy_price' => 'required|numeric',
                    'governorate_code' => 'nullable|string|exists:governorates,code',
                    'source' => 'nullable|string|max:255',
                    'quoted_at' => 'nullable|date',
                    'set_as_default' => 'nullable|boolean',

                ]);

                if ($validator->fails()) {
                    ResponseService::validationError($validator->errors()->first());
                }

                $currencyRate = CurrencyRate::firstOrCreate(
                    ['currency_name' => $request->currency_name],

                    [
                        'sell_price' => 0,
                        'buy_price' => 0,
                        'last_updated_at' => now(),
                    ]
                );

                $wasRecentlyCreated = $currencyRate->wasRecentlyCreated;

                $governorate = null;

                if ($request->filled('governorate_code')) {
                    $governorate = Governorate::where('code', $request->governorate_code)->first();

                    if ($governorate && !$governorate->is_active) {
                        ResponseService::validationError(__('The selected governorate is inactive.'));
                    }
                }

                if (!$governorate) {
                    $defaultQuoteGovernorate = optional(
                        $currencyRate->defaultQuote()->with('governorate')->first()
                    )->governorate;

                    if ($defaultQuoteGovernorate) {
                        $governorate = $defaultQuoteGovernorate;
                    }
                }

                if (!$governorate) {
                    $governorate = Governorate::where('code', 'NATL')->first();
                }

                if (!$governorate) {
                    ResponseService::errorResponse('Governorate not found for the provided currency rate.');
                }

                $quotedAt = $request->filled('quoted_at')
                    ? Carbon::parse($request->quoted_at)
                    : now();

                $existingQuote = CurrencyRateQuote::where('currency_rate_id', $currencyRate->id)
                    ->where('governorate_id', $governorate->id)
                    ->first();

                $shouldBeDefault = $request->has('set_as_default')
                    ? $request->boolean('set_as_default')
                    : (bool) ($existingQuote?->is_default ?? false);

                $quote = CurrencyRateQuote::updateOrCreate(
                    [
                        'currency_rate_id' => $currencyRate->id,
                        'governorate_id' => $governorate->id,
                    ],

                    [
                        'sell_price' => $request->sell_price,
                        'buy_price' => $request->buy_price,
                        'source' => $request->filled('source') ? trim((string) $request->input('source')) : null,
                        'quoted_at' => $quotedAt,
                        'is_default' => $shouldBeDefault,

                        ]
                );


                if ($shouldBeDefault) {
                    $currencyRate->quotes()
                        ->where('id', '!=', $quote->id)
                        ->update(['is_default' => false]);
                } elseif (!$currencyRate->quotes()->where('is_default', true)->exists()) {
                    $quote->is_default = true;
                    $quote->save();
                    $shouldBeDefault = true;
                }

                $quote->refresh();

                if ($quote->is_default) {
                    $currencyRate->applyDefaultQuoteSnapshot($quote);
                } else {
                    $defaultQuote = $currencyRate->defaultQuote()->first();
                    $currencyRate->applyDefaultQuoteSnapshot($defaultQuote);
                }

                $currencyRate->load('quotes.governorate');



                $quotesPayload = $currencyRate->quotes
                    ->map(static function ($quote) {
                        $governorate = $quote->relationLoaded('governorate')
                            ? $quote->governorate
                            : $quote->governorate()->first();

                        return [
                            'governorate_id' => (int) $quote->governorate_id,
                            'governorate_code' => $governorate?->code
                                ? Str::upper((string) $governorate->code)
                                : null,
                            'governorate_name' => $governorate?->name,
                            'sell_price' => $quote->sell_price !== null
                                ? (string) $quote->sell_price
                                : null,
                            'buy_price' => $quote->buy_price !== null
                                ? (string) $quote->buy_price
                                : null,
                            'is_default' => (bool) $quote->is_default,
                        ];
                    })
                    ->values()
                    ->all();

                if ($wasRecentlyCreated) {
                    $defaultGovernorateId = (int) ($currencyRate->quotes
                            ->firstWhere('is_default', true)?->governorate_id
                        ?? $currencyRate->quotes->first()?->governorate_id
                        ?? 0);

                    if ($defaultGovernorateId > 0) {
                        CurrencyCreated::dispatch(
                            $currencyRate->id,
                            $defaultGovernorateId
                        );
                    }
                }

                if (!empty($quotesPayload)) {
                    CurrencyRatesUpdated::dispatch(
                        $currencyRate->id,
                        $quotesPayload
                    );
                }

                ResponseService::successResponse("Currency rate updated successfully.", $currencyRate);
            } catch (Throwable $th) {
                ResponseService::logErrorResponse($th, "API Controller -> updateCurrencyRate");
                ResponseService::errorResponse();
            }
        }
}
