<?php

namespace App\Http\Controllers\Api\Sections\Reviews\NotifyServiceOwnerAboutReview;

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

trait NotifyServiceOwnerAboutReviewTrait
{
    protected function notifyServiceOwnerAboutReview(Service $service, ServiceReview $review, User $reviewer): void
    {
        $ownerId = (int) ($service->owner_id ?? 0);

        if ($ownerId <= 0) {
            return;
        }

        $owner = User::find($ownerId);

        if (!$owner) {
            return;
        }

        $title = __('New service review received');
        $message = __(':user left a new review on ":service".', [
            'user'    => $reviewer->name ?? __('A user'),
            'service' => $service->title ?? __('your service'),
        ]);

        $payload = [
            'service_id'    => $service->id,
            'service_title' => $service->title,
            'review_id'     => $review->id,
            'reviewer_id'   => $reviewer->id,
            'rating'        => $review->rating,
            'review_status' => $review->status,
            'comment'       => $review->review,
        ];

        try {
            $tokens = UserFcmToken::where('user_id', $ownerId)
                ->pluck('fcm_token')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (!empty($tokens) && ($owner->notification ?? true)) {
                $response = NotificationService::sendFcmNotification(
                    $tokens,
                    $title,
                    $message,
                    'service-review',
                    $payload
                );

                if (is_array($response) && ($response['error'] ?? false)) {
                    Log::warning('service_reviews.notification_failed', [
                        'service_id'        => $service->id,
                        'review_id'         => $review->id,
                        'owner_id'          => $ownerId,
                        'response_message'  => $response['message'] ?? null,
                        'response_details'  => $response['details'] ?? null,
                        'response_code'     => $response['code'] ?? null,
                    ]);
                }
            }
        } catch (Throwable $e) {
            Log::error('service_reviews.notification_exception', [
                'service_id'     => $service->id,
                'review_id'      => $review->id,
                'owner_id'       => $ownerId,
                'error'          => $e->getMessage(),
                'exception_class'=> get_class($e),
            ]);
        }

        try {
            Notifications::create([
                'title'   => $title,
                'message' => $message,
                'image'   => '',
                'item_id' => null,
                'send_to' => 'selected',
                'user_id' => (string) $ownerId,
            ]);
        } catch (Throwable $e) {
            Log::error('service_reviews.notification_log_failed', [
                'service_id'     => $service->id,
                'review_id'      => $review->id,
                'owner_id'       => $ownerId,
                'error'          => $e->getMessage(),
                'exception_class'=> get_class($e),
            ]);
        }
    }
}