<?php

namespace App\Http\Controllers\Api\Sections\ManualPayments\BuildManualPaymentMetaUpdates;

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

trait BuildManualPaymentMetaUpdatesTrait
{
    private function buildManualPaymentMetaUpdates(
        Request $request,
        string $paymentMethod,
        bool $isWalletTopUp,
        ?ManualBank $manualBank
    ): array {
        $meta = [
            'source' => 'api.manual_payment_request',
            'submitted_at' => now()->toIso8601String(),
        ];

        $normalizedGateway = strtolower(trim($paymentMethod));
        if ($normalizedGateway !== '') {
            $meta['gateway'] = $normalizedGateway;
        }

        if ($isWalletTopUp) {
            $meta['wallet'] = [
                'purpose' => ManualPaymentRequest::PAYABLE_TYPE_WALLET_TOP_UP,
            ];
        }

        $metadataPayload = $this->extractManualPaymentMetadataPayload($request);
        if ($metadataPayload !== null) {
            $meta['metadata'] = $metadataPayload;
            data_set($meta, 'manual.metadata', $metadataPayload);
        }

        $reference = $this->normalizeManualPaymentString($request->input('reference'));
        if ($reference !== null) {
            $meta['reference'] = $reference;
            data_set($meta, 'manual.reference', $reference);
            if (data_get($meta, 'metadata.reference') === null) {
                data_set($meta, 'metadata.reference', $reference);
            }
            if (data_get($meta, 'metadata.transfer_reference') === null) {
                data_set($meta, 'metadata.transfer_reference', $reference);
            }
        }

        $userNote = $this->normalizeManualPaymentString($request->input('user_note'));
        if ($userNote !== null) {
            $meta['note'] = $userNote;
            $meta['user_note'] = $userNote;
            data_set($meta, 'manual.note', $userNote);
            data_set($meta, 'manual.user_note', $userNote);
            if (data_get($meta, 'metadata.user_note') === null) {
                data_set($meta, 'metadata.user_note', $userNote);
            }
        }

        $transferredAt = $this->normalizeManualPaymentDateValue($request->input('transferred_at'));
        if ($transferredAt !== null) {
            if (data_get($meta, 'metadata.transferred_at') === null) {
                data_set($meta, 'metadata.transferred_at', $transferredAt);
            }
            data_set($meta, 'manual.transferred_at', $transferredAt);
        }

        $manualBankId = null;
        if ($manualBank instanceof ManualBank) {
            $manualBankId = $manualBank->getKey();
        } elseif ($request->filled('manual_bank_id')) {
            $manualBankId = (int) $request->input('manual_bank_id');
        }

        if ($manualBankId !== null && $manualBankId !== 0) {
            data_set($meta, 'bank.id', $manualBankId);
            data_set($meta, 'manual_bank.id', $manualBankId);
        }

        if ($manualBank instanceof ManualBank) {
            $bankName = $this->normalizeManualPaymentString($manualBank->name);
            $beneficiary = $this->normalizeManualPaymentString($manualBank->beneficiary_name);
            $accountNumber = $this->normalizeManualPaymentString($manualBank->account_number ?? $manualBank->iban);
            $currency = $this->normalizeManualPaymentString($manualBank->currency);

            if ($bankName !== null) {
                data_set($meta, 'bank.name', $bankName);
                data_set($meta, 'manual_bank.name', $bankName);
            }

            if ($beneficiary !== null) {
                data_set($meta, 'bank.beneficiary_name', $beneficiary);
                data_set($meta, 'manual_bank.beneficiary_name', $beneficiary);
            }

            if ($accountNumber !== null && data_get($meta, 'bank.account_number') === null) {
                data_set($meta, 'bank.account_number', $accountNumber);
            }

            if ($currency !== null && data_get($meta, 'bank.currency') === null) {
                data_set($meta, 'bank.currency', $currency);
            }
        }

        return $this->cleanupManualPaymentMeta($meta);
    }
}