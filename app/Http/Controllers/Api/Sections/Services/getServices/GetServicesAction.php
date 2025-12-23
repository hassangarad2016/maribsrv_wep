<?php

namespace App\Http\Controllers\Api\Sections\Services\getServices;

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


trait GetServicesAction
{
    public function getServices(Request $request)



    {
        try {
            $validator = Validator::make($request->all(), [
                'id'          => 'nullable|integer|exists:services,id',
                'category_id' => 'nullable|exists:categories,id',
                'categories'  => 'nullable|string', // ids comma separated
                'is_main'     => 'nullable|boolean',
                'service_type'=> 'nullable|string',
                'per_page'    => 'nullable|integer|min:1|max:100',
                'limit'       => 'nullable|integer|min:1|max:100',
                'offset'      => 'nullable|integer|min:0',
                'page'        => 'nullable|integer|min:1',



            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            // أ¢â€‌ع©ط¢ظ¾أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ²أ¢â€¢ع¾أ¢â€“â€™ أ¢â€¢ع¾ط¢آ«أ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط£آ أ¢â€¢ع¾ط·آ± أ¢â€‌ع©ط£ع¾أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾ط·آµأ¢â€¢ع¾ط¢آ»أ¢â€¢ع¾ط·آ± أ¢â€¢ع¾ط·آ°أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط£آ أ¢â€¢ع¾أ¢â€¢آ£أ¢â€¢ع¾أ¢â€“â€™أ¢â€‌ع©ط¸â€کأ¢â€‌ع©ط¢ظ¾ (أ¢â€¢ع¾ط·آ­أ¢â€‌ع©ط¢â€  أ¢â€¢ع¾أ¢â€¢â€“أ¢â€‌ع©ط¢عˆأ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ°)
            if ($request->filled('id')) {
                $s = Service::where('status', true)
                    ->where(function($q){
                        $q->whereNull('expiry_date')->orWhere('expiry_date','>',now());
                    })
                    ->with([
                        'category',
                        'serviceCustomFields.value',
                        'serviceCustomFieldValues',
                        'owner',
                    ])
                    ->withAvg(['reviews as avg_rating' => function ($q) {
                        $q->where('status', ServiceReview::STATUS_APPROVED);
                    }], 'rating')
                    ->withCount(['reviews as reviews_count' => function ($q) {
                        $q->where('status', ServiceReview::STATUS_APPROVED);
                    }])

                    ->findOrFail($request->id);

                $payload = $this->mapService($s);
                ResponseService::successResponse('Service fetched successfully.', $payload);
            }

            // أ¢â€‌ع©ط£آ©أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾ط·آ®أ¢â€‌ع©ط£آ أ¢â€¢ع¾ط·آ± أ¢â€¢ع¾ط¢آ«أ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط£آ أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾ط·آ²
            $query = Service::with([
                    'category',
                    'serviceCustomFields.value',
                    'serviceCustomFieldValues',
                    'owner',
                ])
                ->withAvg(['reviews as avg_rating' => function ($q) {
                    $q->where('status', ServiceReview::STATUS_APPROVED);
                }], 'rating')
                ->withCount(['reviews as reviews_count' => function ($q) {
                    $q->where('status', ServiceReview::STATUS_APPROVED);
                }])

                ->where('status', true)
                ->where(function($q){
                    $q->whereNull('expiry_date')->orWhere('expiry_date','>',now());
                });

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->filled('categories')) {
                $ids = collect(explode(',', $request->categories))
                    ->map(fn($v)=> (int) trim($v))
                    ->filter()->values()->all();
                if (!empty($ids)) $query->whereIn('category_id', $ids);
            }

            if ($request->filled('is_main')) {
                $query->where('is_main', (bool)$request->is_main);
            }

            if ($request->filled('service_type')) {
                $query->where('service_type', $request->service_type);
            }

            $perPageInput = $request->input('per_page', $request->input('limit'));
            $perPage = is_numeric($perPageInput) ? (int) $perPageInput : 15;

            if ($perPage <= 0) {
                $perPage = 15;
            }

            if ($perPage > 100) {
                $perPage = 100;
            }

            $pageInput = $request->input('page');
            $page = is_numeric($pageInput) ? max((int) $pageInput, 1) : null;

            if ($page === null && $request->filled('offset')) {
                $offsetRaw = $request->input('offset');
                $offset = is_numeric($offsetRaw) ? max((int) $offsetRaw, 0) : 0;
                $page = intdiv($offset, $perPage) + 1;
            }

            $services = $query
                ->paginate($perPage, ['*'], 'page', $page)

                ->through(fn(Service $service) => $this->mapService($service));

            $paginationLinks = [
                'first' => $services->url(1),
                'last'  => $services->url($services->lastPage()),
                'prev'  => $services->previousPageUrl(),
                'next'  => $services->nextPageUrl(),
            ];

            $paginationMeta = [
                'current_page' => $services->currentPage(),
                'from'         => $services->firstItem(),
                'last_page'    => $services->lastPage(),
                'path'         => $services->path(),
                'per_page'     => $services->perPage(),
                'to'           => $services->lastItem(),
                'total'        => $services->total(),
            ];

            ResponseService::successResponse(
                'Services fetched successfully.',
                $services->items(),
                [
                    'links' => $paginationLinks,
                    'meta'  => $paginationMeta,
                    'total' => $services->total(),


                ]
            );


        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getServices');
            ResponseService::errorResponse();
        }
    }
}
