<?php

namespace App\Http\Controllers\Api\Sections\Categories\getSubCategories;

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


trait GetSubCategoriesAction
{
    public function getSubCategories(Request $request) {
            $validator = Validator::make($request->all(), [
                'category_id'    => 'nullable|integer',
                'interface_type' => 'nullable|string|max:191',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            try {
                $interfaceTypeFilter = null;
                $allowedCategoryIds = null;

                if ($request->filled('interface_type')) {
                    $interfaceTypeInput = $request->input('interface_type');
                    $interfaceTypeFilter = InterfaceSectionService::canonicalSectionTypeOrNull($interfaceTypeInput)
                        ?? InterfaceSectionService::normalizeSectionType($interfaceTypeInput);

                    if ($interfaceTypeFilter !== null && $interfaceTypeFilter !== 'all') {
                        $resolvedIds = InterfaceSectionService::categoryIdsForSection($interfaceTypeFilter);

                        if (is_array($resolvedIds)) {
                            $resolvedIds = array_values(array_filter(
                                array_map(static fn ($id) => is_numeric($id) ? (int) $id : null, $resolvedIds),
                                static fn ($id) => ! is_null($id) && $id > 0
                            ));

                            if ($resolvedIds !== []) {
                                $allowedCategoryIds = $this->expandCategoryIdsWithDescendants($resolvedIds);
                            }
                        }
                    }
                }

                $baseQuery = Category::withCount(['subcategories' => function ($q) use ($allowedCategoryIds) {
                    $q->where('status', 1);
                    if (! empty($allowedCategoryIds)) {
                        $q->whereIn('id', $allowedCategoryIds);
                    }
                }])->with('translations')->where(['status' => 1])->orderBy('sequence', 'ASC')
                    ->with(['subcategories'          => function ($query) use ($allowedCategoryIds) {
                        $query->where('status', 1)
                            ->orderBy('sequence', 'ASC')
                            ->with('translations')
                            ->withCount(['approved_items', 'subcategories' => function ($q) use ($allowedCategoryIds) {
                                $q->where('status', 1);
                                if (! empty($allowedCategoryIds)) {
                                    $q->whereIn('id', $allowedCategoryIds);
                                }
                            }]); // Order subcategories by 'sequence'

                        if (! empty($allowedCategoryIds)) {
                            $query->whereIn('id', $allowedCategoryIds);
                        }
                    }, 'subcategories.subcategories' => function ($query) use ($allowedCategoryIds) {
                        $query->where('status', 1)
                            ->orderBy('sequence', 'ASC')
                            ->with('translations')
                            ->withCount(['approved_items', 'subcategories' => function ($q) use ($allowedCategoryIds) {
                                $q->where('status', 1);
                                if (! empty($allowedCategoryIds)) {
                                    $q->whereIn('id', $allowedCategoryIds);
                                }
                            }]);

                        if (! empty($allowedCategoryIds)) {
                            $query->whereIn('id', $allowedCategoryIds);
                        }
                    }]);

                if (! empty($allowedCategoryIds)) {
                    $baseQuery->whereIn('id', $allowedCategoryIds);
                }

                if (!empty($request->category_id)) {
                    $category = (clone $baseQuery)
                        ->where('id', $request->category_id)
                        ->firstOrFail();

                    $category->all_items_count = $category->all_items_count;

                    $childrenPaginator = (clone $baseQuery)
                        ->where('parent_category_id', $request->category_id)
                        ->paginate();

                    $childrenPaginator->getCollection()->transform(function ($category) {
                        $category->all_items_count = $category->all_items_count;
                        return $category;
                    });

                    ResponseService::successResponse(null, $childrenPaginator, [
                        'self_category'  => $category,
                        'append_to_data' => ['self_category' => $category],
                    ]);

                    return;


                }

                $paginator = $baseQuery->whereNull('parent_category_id')->paginate();
                $paginator->getCollection()->transform(function ($category) {


                    $category->all_items_count = $category->all_items_count;
                    return $category;
                });
                ResponseService::successResponse(null, $paginator, [
                    'self_category'  => null,
                    'append_to_data' => ['self_category' => null],
                ]);

            } catch (Throwable $th) {
                ResponseService::logErrorResponse($th, 'API Controller -> getCategories');
                ResponseService::errorResponse();
            }
        }
}
