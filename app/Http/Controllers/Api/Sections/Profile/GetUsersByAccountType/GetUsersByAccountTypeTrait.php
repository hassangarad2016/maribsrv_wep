<?php

namespace App\Http\Controllers\Api\Sections\Profile\GetUsersByAccountType;

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

trait GetUsersByAccountTypeTrait
{
    public function getUsersByAccountType(Request $request) {
        try {
            $accountType = $request->integer('account_type');
            if ($accountType === null) {
                $accountType = User::ACCOUNT_TYPE_SELLER;
            }

        
            $allowedAccountTypes = [
                User::ACCOUNT_TYPE_CUSTOMER,
                User::ACCOUNT_TYPE_REAL_ESTATE,
                User::ACCOUNT_TYPE_SELLER,
            ];

            if (! in_array($accountType, $allowedAccountTypes, true)) {
                
                return response()->json([
                    'error' => true,
                    'message' => __('â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´â”¬ط¨â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھطھâ”¬ط« â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â•،â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â”œطھط¸أ©ط´â”Œظ‘â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–‘ â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط¨â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ”œطھط¸أ©ط´ط¸آ€آ£â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–‘ â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ”œطھط¸أ©ط´â”Œط±â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط°â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€آ£â”œطھط¸أ©ط´ط¸آ„طھ â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•–â”¼ظ’â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â•،.'),
                ], 422);
            }


            $usersQuery = User::query()
                ->with(['store'])
                ->where('account_type', $accountType)
                ->orderByDesc('updated_at');

            // If per_page is not provided, return all matching users (with a reasonable cap).
            $perPage = $request->integer('per_page');
            if ($perPage === null || $perPage <= 0) {
                $perPage = (clone $usersQuery)->count();
            }
            if ($perPage <= 0) {
                $perPage = 50;
            }
            $perPage = (int) min($perPage, 500);

            $users = $usersQuery->paginate($perPage);

            // Enrich store data with media URLs/counts so client can render real cover/logo.
            $users->getCollection()->transform(function (User $user) {
                if ($user->relationLoaded('store') && $user->store) {
                    $store = $user->store;
                    if (! $store->getAttribute('banner_url')) {
                        $bannerPath = $store->getAttribute('banner_path');
                        $store->setAttribute(
                            'banner_url',
                            $bannerPath
                                ? (preg_match('#^https?://#i', $bannerPath) ? $bannerPath : Storage::url($bannerPath))
                                : null
                        );
                    }
                    if (! $store->getAttribute('logo_url')) {
                        $logoPath = $store->getAttribute('logo_path');
                        $store->setAttribute(
                            'logo_url',
                            $logoPath
                                ? (preg_match('#^https?://#i', $logoPath) ? $logoPath : Storage::url($logoPath))
                                : null
                        );
                    }
                    // Fallback: use logo as banner if no cover uploaded.
                    if (! $store->getAttribute('banner_url') && $store->getAttribute('logo_url')) {
                        $store->setAttribute('banner_url', $store->getAttribute('logo_url'));
                    }
                    $store->setAttribute(
                        'followers_count',
                        $store->getAttribute('followers_count') ?? $store->followers()->count()
                    );
                    $store->setAttribute(
                        'items_count',
                        $store->getAttribute('items_count') ?? $store->items()->count()
                    );
                }

                return $user;
            });

            return response()->json([
                'error' => false,
                'message' => __('â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–“â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط¨ â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â”¤â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–‘ â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â•،â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â”œطھط¸أ©ط´â”Œظ‘â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–‘â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–“ â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–‘â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´â”¬ط¨â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â”¤â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â•،.'),
                'data' => $users,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => true,
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}