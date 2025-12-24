<?php

namespace App\Http\Controllers\Api\Sections\Items\FormatSummaryResult;

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

trait FormatSummaryResultTrait
{
    protected function formatSummaryResult($result): array
    {
        $transformItem = static function (Item $item): array {
            $thumbnail = $item->thumbnail_url ?? $item->image;
            $finalPrice = $item->calculateDiscountedPrice();
            $discountSnapshot = $item->discount_snapshot;
            $featuredCount = $item->featured_items_count ?? 0;
            $favouritesCount = $item->favourites_count ?? 0;
            $isFavorited = $item->getAttribute('is_favorited');
            if ($isFavorited === null) {
                $isFavorited = $item->is_favorited ?? null;
            }

            $isLiked = (bool) ($isFavorited ?? false);

            return [
                'id' => $item->id,
                'name' => $item->name,
                'slug' => $item->slug,
                'price' => $item->price,
                'final_price' => $finalPrice,
                'currency' => $item->currency,
                'thumbnail_url' => $thumbnail,
                'thumbnail_fallback_url' => $item->image,
                'image' => $item->image,
                'created_at' => optional($item->created_at)->toIso8601String(),
                'updated_at' => optional($item->updated_at)->toIso8601String(),
                'city' => $item->city,
                'state' => $item->state,
                'country' => $item->country,
                'address' => $item->address,
                'latitude' => $item->latitude,
                'longitude' => $item->longitude,
                'status' => $item->status,
                'type' => $item->getAttribute('type') ?? $item->item_type ?? $item->interface_type,
                'item_type' => $item->item_type,
                'user_id' => $item->user_id,
                'category_id' => $item->category_id,
                'product_link' => $item->product_link,
                'discount' => $discountSnapshot,
                'total_likes' => (int) $favouritesCount,
                'is_liked' => $isLiked,
                'is_feature' => (int) $featuredCount > 0,
                'clicks' => $item->clicks,
            ];

        };

        if ($result instanceof AbstractPaginator) {
            $collection = $result->getCollection();
            $items = $collection->map($transformItem)->values()->all();
            $paginatorArray = $result->toArray();

            $meta = [
                'current_page' => $paginatorArray['current_page'] ?? null,
                'from' => $paginatorArray['from'] ?? null,
                'last_page' => $paginatorArray['last_page'] ?? null,
                'per_page' => $paginatorArray['per_page'] ?? null,
                'to' => $paginatorArray['to'] ?? null,
                'total' => $paginatorArray['total'] ?? null,
            ];

            if (method_exists($result, 'hasMorePages')) {
                $meta['has_more_pages'] = $result->hasMorePages();
            }

            if (method_exists($result, 'hasPages')) {
                $meta['has_pages'] = $result->hasPages();
            }

            $links = [
                'first_page_url' => $paginatorArray['first_page_url'] ?? null,
                'last_page_url' => $paginatorArray['last_page_url'] ?? null,
                'next_page_url' => $paginatorArray['next_page_url'] ?? null,
                'prev_page_url' => $paginatorArray['prev_page_url'] ?? null,
                'path' => $paginatorArray['path'] ?? null,
            ];

            $pagination = array_merge($meta, $links);
            $pagination['links'] = $paginatorArray['links'] ?? [];

            return [
                'items' => $items,
                'meta' => $meta,
                'links' => $links,
                'link_items' => $paginatorArray['links'] ?? [],
                'pagination' => $pagination,
            ];
        }

        if ($result instanceof Collection) {
            return [
                'items' => $result->map($transformItem)->values()->all(),
            ];
        } 

        return ['items' => collect($result)->map($transformItem)->values()->all()];
    }
}