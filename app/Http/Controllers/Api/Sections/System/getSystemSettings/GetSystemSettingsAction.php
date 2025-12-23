<?php

namespace App\Http\Controllers\Api\Sections\System\getSystemSettings;

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


trait GetSystemSettingsAction
{
    public function getSystemSettings(Request $request) {
            try {
                $perPage = $request->integer('per_page', 15) ?? 15;

                if ($perPage <= 0) {
                    $perPage = 15;
                }

                $perPage = min($perPage, 50);


                $settingsQuery = Setting::select(['name', 'value', 'type'])->orderBy('name');


                $typeFilter = $this->normalizeSettingNames($request->input('type'));
                if (!empty($typeFilter)) {
                    $settingsQuery->whereIn('name', $typeFilter);
                }
                $fieldsFilter = $this->normalizeSettingNames($request->input('fields'));
                if (!empty($fieldsFilter)) {
                    $settingsQuery->whereIn('name', $fieldsFilter);
                }

                $settings = $settingsQuery
                    ->paginate($perPage)
                    ->withQueryString()
                    ->through(static function (Setting $row): array {
                        return [
                            'name'  => $row->name,
                            'value' => $row->value,
                            'type'  => $row->type,
                        ];
                    });

                $settingsCollection = $settings->getCollection();



                $currentValues = $settingsCollection
                    ->filter(static function ($item) {
                        if (!is_array($item)) {
                            return false;
                        }

                        $name = $item['name'] ?? null;


                        return is_string($name) && $name !== '';
                    })
                    ->mapWithKeys(static fn (array $item) => [
                        $item['name'] => $item['value'] ?? null,
                    ])
                    ->all();

                $requiredKeys = $this->socialLinkSettingKeys();
                $hydratedValues = $this->hydrateSettingValues($requiredKeys, $currentValues);

                $socialLinks = $this->buildSocialLinks($hydratedValues);




                $supportService = app(DepartmentSupportService::class);


                $extras = [
                    'social_links' => $socialLinks,
                    'department_support' => $supportService->allWhatsAppSupport(),
                    'demo_mode' => config('app.demo_mode'),
                    'languages' => CachingService::getLanguages(),
                    'admin' => User::role('Super Admin')->select(['name', 'profile'])->first(),
                ];

                ResponseService::successResponse(
                    "Data Fetched Successfully",
                    $settings,
                    [
                        'items_key' => 'settings',
                        'append_to_data' => [
                            'extras' => $extras,
                        ],
                    ]
                );

            } catch (Throwable $th) {
                ResponseService::logErrorResponse($th, "API Controller -> getSystemSettings");
                ResponseService::errorResponse();
            }
        }
}
