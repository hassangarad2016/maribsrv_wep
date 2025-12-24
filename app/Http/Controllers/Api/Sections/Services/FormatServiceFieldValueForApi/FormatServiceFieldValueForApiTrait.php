<?php

namespace App\Http\Controllers\Api\Sections\Services\FormatServiceFieldValueForApi;

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

trait FormatServiceFieldValueForApiTrait
{
private function formatServiceFieldValueForApi(ServiceCustomField $field, ?ServiceCustomFieldValue $valueModel): array
{
    $type = $field->normalizedType();

    $result = [
        'value' => match ($type) {
            'checkbox' => [],
            'fileinput' => [],
            default => null,
        },
        'value_id' => null,
        'value_updated_at' => null,
    ];

    if (!$valueModel) {
        return $result;
    }

    $result['value_id'] = $valueModel->id;
    $result['value_updated_at'] = $valueModel->updated_at?->toISOString();

    $decoded = $valueModel->value;
    $rawOriginal = $valueModel->getRawOriginal('value');

    if ($type === 'checkbox') {
        $values = [];
        if (is_array($decoded)) {
            $values = array_values(array_filter($decoded, static fn($v) => $v !== null && $v !== ''));
        } elseif ($decoded !== null && $decoded !== '') {
            $values = [(string) $decoded];
        } elseif (is_string($rawOriginal) && $rawOriginal !== '') {
            $json = json_decode($rawOriginal, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                $values = array_values(array_filter($json, static fn($v) => $v !== null && $v !== ''));
            }
        }

        $result['value'] = $values;
        if (!empty($values)) {
            $result['display_value'] = implode(', ', $values);
        }
        if (!empty($values)) {
            $result['value_raw'] = $values;
        }

        return $result;
    }

    if ($type === 'fileinput') {
        $rawValues = [];
        if (is_array($decoded)) {
            $rawValues = array_values(array_filter($decoded, static fn($v) => $v !== null && $v !== ''));
        } elseif (is_string($decoded) && $decoded !== '') {
            $rawValues = [$decoded];
        } elseif (is_string($rawOriginal) && $rawOriginal !== '') {
            $json = json_decode($rawOriginal, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                $rawValues = array_values(array_filter($json, static fn($v) => $v !== null && $v !== ''));
            } else {
                $rawValues = [$rawOriginal];
            }
        }

        $fileUrls = array_values(array_filter(array_map(static function ($path) {
            if (!is_string($path) || $path === '') {
                return null;
            }

            if (preg_match('#^https?://#i', $path)) {
                return $path;
            }

            return asset('storage/' . ltrim($path, '/'));
        }, $rawValues)));

        $result['value'] = $fileUrls;
        if (!empty($rawValues)) {
            $result['value_raw'] = count($rawValues) === 1 ? $rawValues[0] : $rawValues;
        }
        if (!empty($fileUrls)) {
            $result['file_urls'] = $fileUrls;
            $result['file_url'] = $fileUrls[0];
        }

        return $result;
    }

    if (is_array($decoded)) {
        $filtered = array_values(array_filter($decoded, static fn($v) => $v !== null && $v !== ''));
        $value = $filtered[0] ?? null;
        $result['value'] = $value;
        if (!empty($filtered)) {
            $result['display_value'] = implode(', ', $filtered);
            $result['value_raw'] = $filtered;
        }
    } else {
        $value = ($decoded !== null && $decoded !== '') ? (string) $decoded : null;
        $result['value'] = $value;
        if ($value !== null) {
            $result['display_value'] = $value;
            $result['value_raw'] = $decoded;
        }
    }

    if ($result['value_raw'] === null && is_string($rawOriginal) && $rawOriginal !== '') {
        $json = json_decode($rawOriginal, true);
        $result['value_raw'] = json_last_error() === JSON_ERROR_NONE ? $json : $rawOriginal;
    }

    return $result;
}
}