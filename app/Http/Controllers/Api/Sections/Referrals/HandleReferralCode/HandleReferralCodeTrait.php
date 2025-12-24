<?php

namespace App\Http\Controllers\Api\Sections\Referrals\HandleReferralCode;

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

trait HandleReferralCodeTrait
{
    private function handleReferralCode($code, $user, $contactInfo, array $locationPayload = [], array $requestMeta = [])
    {


        $lat = $locationPayload['lat'] ?? null;
        $lng = $locationPayload['lng'] ?? null;
        $deviceTimeRaw = $locationPayload['device_time'] ?? null;
        $adminArea = $locationPayload['admin_area'] ?? null;

        $requestIp = $requestMeta['ip'] ?? null;
        $userAgent = $requestMeta['user_agent'] ?? null;
        $additionalMeta = $requestMeta;
        unset($additionalMeta['ip'], $additionalMeta['user_agent']);


        $deviceTime = null;

        if ($deviceTimeRaw !== null && $deviceTimeRaw !== '') {
            try {
                $deviceTime = Carbon::parse($deviceTimeRaw)->toIso8601String();
            } catch (Throwable) {
                $deviceTime = (string) $deviceTimeRaw;
            }
        }

        $auditContext = [
            'code' => $code,
            'referrer_id' => null,
            'referred_user_id' => $user?->id,
            'contact' => $contactInfo,
            'lat' => $lat,
            'lng' => $lng,
            'device_time' => $deviceTime,
            'admin_area' => $adminArea,
            'request_ip' => $requestIp,
            'user_agent' => $userAgent,

        ];
        if (!empty($additionalMeta)) {
            $auditContext['meta'] = $additionalMeta;
        }



        $baseResponse = [
            'code' => $code,
            'referrer_id' => null,
            'referred_user_id' => $user?->id,
            'contact' => $contactInfo,
            'location' => [
                'lat' => $lat,
                'lng' => $lng,
                'device_time' => $deviceTime,
                'admin_area' => $adminArea,
            ],
            'request' => array_filter([
                'ip' => $requestIp,
                'user_agent' => $userAgent,
                'meta' => !empty($additionalMeta) ? $additionalMeta : null,
            ]),

        ];


        try {
            // â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–‘â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â•،â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â”‚ â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھطھâ”¬ط«â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´â”¬ط¨ â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط¨â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â”œطھط¸أ©ط´â”Œظ‘â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–“â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬طھâ•ھطھâ”¬ط³â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬طھâ•ھطھâ”¬â•—â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط¨ â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€آ£â”œطھط¸أ©ط´â”Œط±â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط° â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط°â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط¨â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬طھ â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬طھâ•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬طھâ•ھطھâ”¬â•— â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬طµâ•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â•،â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–’
            $referrer = User::where('referral_code', $code)->first();
            
            if (!$referrer) {
                $attempt = $this->referralAuditLogger->record('invalid_code', $auditContext);

                return array_merge($baseResponse, [
                    'status' => 'invalid_code',
                    'message' => 'Invalid referral code.',
                    'attempt' => $this->formatReferralAttempt($attempt),


                ]);
            
            
            }
            
            $auditContext['referrer_id'] = $referrer->id;
            $baseResponse['referrer_id'] = $referrer->id;

            if (Referral::where('referred_user_id', $user->id)->exists()) {
                $attempt = $this->referralAuditLogger->record('duplicate', $auditContext);

                return array_merge($baseResponse, [
                    'status' => 'duplicate',
                    'message' => 'Referral has already been processed for this user.',
                    'attempt' => $this->formatReferralAttempt($attempt),


                ]);


            }
            
            // â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â•،â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•–â”¼ظ’â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچ â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھطھâ”¬ط«â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط³ â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬ط³â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچ â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–“â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â•،â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬طھâ•ھطھâ”¬â•—â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط° â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´â”¬ط¨â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھطھâ”¬ط¬â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ”œطھط¸أ©ط´ط¸آ€آ£


            if ($lat === null || $lng === null || $deviceTime === null) {
                $attempt = $this->referralAuditLogger->record('location_denied', $auditContext);

                return array_merge($baseResponse, [
                    'status' => 'location_denied',
                    'message' => 'Location permissions are required to apply referral rewards.',
                    'attempt' => $this->formatReferralAttempt($attempt),


                ]);
            }

            if (!$this->maribBoundaryService->contains($lat, $lng)) {

                $notificationMeta = $this->sendReferralStatusNotification(
                    $referrer,
                    $user,
                    'notifications.referral.outside_marib'
                );

                if (!empty($notificationMeta)) {
                    $auditContext['notification'] = $notificationMeta;
                }

                $attempt = $this->referralAuditLogger->record('outside_marib', $auditContext);

                return array_merge($baseResponse, [
                    'status' => 'outside_marib',
                    'message' => 'Referral attempt is outside the Marib service boundary.',
                    'attempt' => $this->formatReferralAttempt($attempt),


                ]);
            }

            $challenge = Challenge::where('is_active', true)
                ->where('required_referrals', '>', 0)
                ->orderBy('id', 'asc')
                ->first();
            
            if (!$challenge) {
                $attempt = $this->referralAuditLogger->record('no_active_challenge', $auditContext);

                return array_merge($baseResponse, [
                    'status' => 'no_active_challenge',
                    'message' => 'No active referral challenges are available.',
                    'attempt' => $this->formatReferralAttempt($attempt),


                ]);
                
            }
            
            // â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬طµâ•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´â”¬ط¨â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھطھâ”¬ط¬â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬ط± â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â”œطھط¸أ©ط´â”Œظ‘â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â”¤â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچ â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬طµâ•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â•،â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–’ â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط¨â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھطھâ”¬ط« challenge_id â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھâ•£â”¬â•› points
                 $referral = Referral::create([
                'referrer_id' => $referrer->id,
                'referred_user_id' => $user->id,
                'challenge_id' => $challenge->id,
                'points' => $challenge->points_per_referral,
            ]);
            
            // â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–“â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط±â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط°â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچ â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھطھâ”¬ط«â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬طھâ•ھطھâ”¬â•—â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬طھâ•ھطھâ”¬â•— â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬طµâ•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â•،â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–“ â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط¨â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ”œطھط¸أ©ط´ط¸آ€آ£â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–‘â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–’ â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–‘â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط¨â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط±â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬طھâ•ھطھâ”¬â•—â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€آ£â”œطھط¸أ©ط´ط¸آ„طھ â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â•،â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬طھâ•ھطھâ”¬â•—
            $challenge->decrement('required_referrals');
            
            $auditContext['referral_id'] = $referral->id;
            $auditContext['challenge_id'] = $challenge->id;
            $auditContext['awarded_points'] = $challenge->points_per_referral;

            $notificationMeta = $this->sendReferralStatusNotification(
                $referrer,
                $user,
                'notifications.referral.accepted'
            );

            if (!empty($notificationMeta)) {
                $auditContext['notification'] = $notificationMeta;
            }


            $attempt = $this->referralAuditLogger->record('ok', $auditContext);

            return array_merge($baseResponse, [
                'status' => 'ok',
                'message' => 'Referral recorded successfully.',
                'referral_id' => $referral->id,
                'challenge_id' => $challenge->id,
                'awarded_points' => (int) $challenge->points_per_referral,
                'attempt' => $this->formatReferralAttempt($attempt),


            ]);
            
            
        } catch (Throwable $th) {
            $attempt = $this->referralAuditLogger->record('error', array_merge($auditContext, [
                'exception_message' => $th->getMessage(),
            ]));

            \Log::error('Error processing referral code: ' . $th->getMessage(), [
                'code' => $code,
                'user_id' => $user?->id,
            ]);

            return array_merge($baseResponse, [
                'status' => 'error',
                'message' => 'Unable to process referral code at this time.',
                'attempt' => $this->formatReferralAttempt($attempt),


            ]);
        
        
        }
    }
}