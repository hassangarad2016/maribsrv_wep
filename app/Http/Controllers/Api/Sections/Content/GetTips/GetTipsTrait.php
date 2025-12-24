<?php

namespace App\Http\Controllers\Api\Sections\Content\GetTips;

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

trait GetTipsTrait
{
     public function getTips(Request $request)
     {
        $validator = Validator::make($request->all(), [
            'department' => ['required', Rule::in(config('cart.departments', []))],
            'item_id' => ['nullable', 'integer', 'exists:items,id'],
        ]);

        if ($validator->fails()) {
            return ResponseService::validationError($validator->errors()->first());
        }

        $departmentKey = $request->input('department');
        $itemId = $request->input('item_id');

        app(TelemetryService::class)->record('api.tips.called', [
            'department' => $departmentKey,
            'item_id' => $itemId,
        ]);

        $departments = app(DepartmentReportService::class)->availableDepartments();
        $departmentResolver = app(DepartmentAdvertiserService::class);

        $itemDepartment = null;
        $productLink = null;
        $reviewLink = null;

        $storePolicyText = null;

        if ($request->filled('item_id')) {
            $item = Item::with([
                'store.policies' => static function ($query) {
                    $query->where('is_active', true);
                },
            ])->select([
                'id',
                'product_link',
                'review_link',
                'interface_type',
                'category_id',
                'all_category_ids',
                'store_id',
            ])->find($request->integer('item_id'));

            if ($item !== null) {
                $itemDepartment = $departmentResolver->resolveDepartmentForItem($item);
                $productLink = $item->product_link;
                $reviewLink = $item->review_link;
                $storePolicyText = $this->buildStorePolicySummary($item->store);
            }
        }

        Log::info('tips.department_check', [
            'item_id' => $itemId,
            'requested_department' => $departmentKey,
            'item_department' => $itemDepartment,
        ]);


        $tips = Tip::with('translations.language')
            ->where('department', $departmentKey)
            ->orderBy('sequence')
            ->get();

        $tipsPayload = $tips->map(function (Tip $tip) {
            $translations = $tip->translations->mapWithKeys(static function (TipTranslation $translation) {
                $code = $translation->language?->code;

                if (empty($code)) {
                    return [];
                }

                return [$code => $translation->description];
            });

            return [
                'id' => $tip->id,
                'department' => $tip->department,
                'sequence' => $tip->sequence,
                'description' => $tip->translated_name,
                'default_description' => $tip->description,
                'translations' => $translations,
            ];
        })->values();

        $response = [
            'department' => [
                'key' => $departmentKey,
                'label' => $departments[$departmentKey] ?? $departmentKey,
            ],
            'tips' => $tipsPayload,
            'product_link' => null,
            'actions' => [],
            'review_link' => null,


            'presentation' => DepartmentReportService::DEPARTMENT_SHEIN === $departmentKey ? 'modal' : 'banner',


        ];

        if ($departmentKey === DepartmentReportService::DEPARTMENT_SHEIN) {
            $response['product_link'] = $productLink;
            $response['review_link'] = $reviewLink;

            $verificationLink = $reviewLink ?: $productLink;

            $response['actions'] = array_values(array_filter([


                [
                    'type' => 'navigate',
                    'target' => 'cart',
                    'label' => __('Continue Purchase'),
                ],
                $verificationLink ? [

                    'type' => 'open_url',
                    'url' => $verificationLink,

                    'label' => __('Verify Product'),
                    'payload' => array_filter([
                        'review_link' => $reviewLink,
                        'product_link' => $productLink,
                    ]),
                ] : null,
            ])); 
        }

        if ($storePolicyText !== null && ! isset($response['return_policy_text'])) {
            $response['return_policy_text'] = $storePolicyText;
        }


        $response['item_department'] = $itemDepartment;

        app(TelemetryService::class)->record('api.tips.response', [
            'department' => $departmentKey,
            'item_id' => $itemId,
            'tips_count' => $tipsPayload->count(),
            'actions_count' => count($response['actions']),
            'has_product_link' => $response['product_link'] !== null,
            'has_review_link' => $response['review_link'] !== null,


        ]);

        Log::info('tips.response_payload', [
            'department' => $departmentKey,
            'item_id' => $itemId,
            'tips_count' => $tipsPayload->count(),
            'actions_count' => count($response['actions']),
            'product_link' => $response['product_link'],
            'review_link' => $response['review_link'],


        ]);



        return ResponseService::successResponse('Tips fetched successfully.', $response);
     }
}