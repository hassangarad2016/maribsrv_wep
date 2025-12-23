<?php
 
namespace App\Http\Controllers;

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


class ApiController extends Controller {

    use \App\Http\Controllers\Api\Sections\Items\addItem\AddItemAction;
    use \App\Http\Controllers\Api\Sections\Reviews\addItemReview\AddItemReviewAction;
    use \App\Http\Controllers\Api\Sections\Reports\addReports\AddReportsAction;
    use \App\Http\Controllers\Api\Sections\Reports\addReviewReport\AddReviewReportAction;
    use \App\Http\Controllers\Api\Sections\Reviews\addServiceReview\AddServiceReviewAction;
    use \App\Http\Controllers\Api\Sections\Reports\addServiceReviewReport\AddServiceReviewReportAction;
    use \App\Http\Controllers\Api\Sections\Packages\assignFreePackage\AssignFreePackageAction;
    use \App\Http\Controllers\Api\Sections\Users\blockUser\BlockUserAction;
    use \App\Http\Controllers\Api\Sections\Auth\completeRegistration\CompleteRegistrationAction;
    use \App\Http\Controllers\Api\Sections\Items\createItemOffer\CreateItemOfferAction;
    use \App\Http\Controllers\Api\Sections\Items\deleteItem\DeleteItemAction;
    use \App\Http\Controllers\Api\Sections\Services\deleteOwnedService\DeleteOwnedServiceAction;
    use \App\Http\Controllers\Api\Sections\Users\deleteUser\DeleteUserAction;
    use \App\Http\Controllers\Api\Sections\Blog\getAllBlogTags\GetAllBlogTagsAction;
    use \App\Http\Controllers\Api\Sections\Sections\getAllowedSections\GetAllowedSectionsAction;
    use \App\Http\Controllers\Api\Sections\Locations\getAreas\GetAreasAction;
    use \App\Http\Controllers\Api\Sections\Users\getBlockedUsers\GetBlockedUsersAction;
    use \App\Http\Controllers\Api\Sections\Blog\getBlog\GetBlogAction;
    use \App\Http\Controllers\Api\Sections\Challenges\getChallenges\GetChallengesAction;
    use \App\Http\Controllers\Api\Sections\Chat\getChatList\GetChatListAction;
    use \App\Http\Controllers\Api\Sections\Chat\getChatMessages\GetChatMessagesAction;
    use \App\Http\Controllers\Api\Sections\Locations\getCities\GetCitiesAction;
    use \App\Http\Controllers\Api\Sections\Locations\getCountries\GetCountriesAction;
    use \App\Http\Controllers\Api\Sections\Currency\getCurrencyRates\GetCurrencyRatesAction;
    use \App\Http\Controllers\Api\Sections\Categories\getCustomFields\GetCustomFieldsAction;
    use \App\Http\Controllers\Api\Sections\Delivery\getDeliveryPrices\GetDeliveryPricesAction;
    use \App\Http\Controllers\Api\Sections\Faq\getFaqs\GetFaqsAction;
    use \App\Http\Controllers\Api\Sections\Favourites\getFavouriteItem\GetFavouriteItemAction;
    use \App\Http\Controllers\Api\Sections\FeaturedAds\getFeaturedAdsCount\GetFeaturedAdsCountAction;
    use \App\Http\Controllers\Api\Sections\Sections\getFeaturedSection\GetFeaturedSectionAction;
    use \App\Http\Controllers\Api\Sections\Sections\getFeaturedSections\GetFeaturedSectionsAction;
    use \App\Http\Controllers\Api\Sections\Items\getItem\GetItemAction;
    use \App\Http\Controllers\Api\Sections\Items\getItemBuyerList\GetItemBuyerListAction;
    use \App\Http\Controllers\Api\Sections\Languages\getLanguages\GetLanguagesAction;
    use \App\Http\Controllers\Api\Sections\Packages\getLimits\GetLimitsAction;
    use \App\Http\Controllers\Api\Sections\Services\getManagedService\GetManagedServiceAction;
    use \App\Http\Controllers\Api\Sections\ManualPayments\getManualBanks\GetManualBanksAction;
    use \App\Http\Controllers\Api\Sections\ManualPayments\getManualPaymentRequests\GetManualPaymentRequestsAction;
    use \App\Http\Controllers\Api\Sections\Reviews\getMyReview\GetMyReviewAction;
    use \App\Http\Controllers\Api\Sections\Reviews\getMyServiceReviews\GetMyServiceReviewsAction;
    use \App\Http\Controllers\Api\Sections\Notifications\getNotificationList\GetNotificationListAction;
    use \App\Http\Controllers\Api\Sections\Services\getOwnedServices\GetOwnedServicesAction;
    use \App\Http\Controllers\Api\Sections\Packages\getPackage\GetPackageAction;
    use \App\Http\Controllers\Api\Sections\Categories\getParentCategoryTree\GetParentCategoryTreeAction;
    use \App\Http\Controllers\Api\Sections\Payments\getPaymentIntent\GetPaymentIntentAction;
    use \App\Http\Controllers\Api\Sections\Payments\getPaymentSettings\GetPaymentSettingsAction;
    use \App\Http\Controllers\Api\Sections\Payments\getPaymentTransactions\GetPaymentTransactionsAction;
    use \App\Http\Controllers\Api\Sections\Reports\getReportReasons\GetReportReasonsAction;
    use \App\Http\Controllers\Api\Sections\Users\getSeller\GetSellerAction;
    use \App\Http\Controllers\Api\Sections\Reviews\getServiceReviews\GetServiceReviewsAction;
    use \App\Http\Controllers\Api\Sections\Services\getServices\GetServicesAction;
    use \App\Http\Controllers\Api\Sections\Slider\getSlider\GetSliderAction;
    use \App\Http\Controllers\Api\Sections\Categories\getSpecificCategories\GetSpecificCategoriesAction;
    use \App\Http\Controllers\Api\Sections\Locations\getStates\GetStatesAction;
    use \App\Http\Controllers\Api\Sections\Categories\getSubCategories\GetSubCategoriesAction;
    use \App\Http\Controllers\Api\Sections\System\getSystemSettings\GetSystemSettingsAction;
    use \App\Http\Controllers\Api\Sections\Content\getTips\GetTipsAction;
    use \App\Http\Controllers\Api\Sections\Orders\getUserOrders\GetUserOrdersAction;
    use \App\Http\Controllers\Api\Sections\Profile\getUserProfileStats\GetUserProfileStatsAction;
    use \App\Http\Controllers\Api\Sections\Challenges\getUserReferralPoints\GetUserReferralPointsAction;
    use \App\Http\Controllers\Api\Sections\Users\getUsersByAccountType\GetUsersByAccountTypeAction;
    use \App\Http\Controllers\Api\Sections\Verification\getVerificationFields\GetVerificationFieldsAction;
    use \App\Http\Controllers\Api\Sections\Verification\getVerificationMetadata\GetVerificationMetadataAction;
    use \App\Http\Controllers\Api\Sections\Verification\getVerificationRequest\GetVerificationRequestAction;
    use \App\Http\Controllers\Api\Sections\Payments\inAppPurchase\InAppPurchaseAction;
    use \App\Http\Controllers\Api\Sections\FeaturedAds\makeFeaturedItem\MakeFeaturedItemAction;
    use \App\Http\Controllers\Api\Sections\Favourites\manageFavourite\ManageFavouriteAction;
    use \App\Http\Controllers\Api\Sections\Chat\markMessageDelivered\MarkMessageDeliveredAction;
    use \App\Http\Controllers\Api\Sections\Chat\markMessageRead\MarkMessageReadAction;
    use \App\Http\Controllers\Api\Sections\Slider\recordSliderClick\RecordSliderClickAction;
    use \App\Http\Controllers\Api\Sections\Items\renewItem\RenewItemAction;
    use \App\Http\Controllers\Api\Sections\Profile\saveUserLocation\SaveUserLocationAction;
    use \App\Http\Controllers\Api\Sections\Chat\sendMessage\SendMessageAction;
    use \App\Http\Controllers\Api\Sections\Auth\sendOtp\SendOtpAction;
    use \App\Http\Controllers\Api\Sections\Verification\sendVerificationRequest\SendVerificationRequestAction;
    use \App\Http\Controllers\Api\Sections\Seo\seoSettings\SeoSettingsAction;
    use \App\Http\Controllers\Api\Sections\Items\setItemTotalClick\SetItemTotalClickAction;
    use \App\Http\Controllers\Api\Sections\ManualPayments\showManualPaymentRequest\ShowManualPaymentRequestAction;
    use \App\Http\Controllers\Api\Sections\Wallet\showWalletWithdrawalRequest\ShowWalletWithdrawalRequestAction;
    use \App\Http\Controllers\Api\Sections\Contact\storeContactUs\StoreContactUsAction;
    use \App\Http\Controllers\Api\Sections\ManualPayments\storeManualPaymentRequest\StoreManualPaymentRequestAction;
    use \App\Http\Controllers\Api\Sections\Users\storeRequestDevice\StoreRequestDeviceAction;
    use \App\Http\Controllers\Api\Sections\Wallet\storeWalletWithdrawalRequest\StoreWalletWithdrawalRequestAction;
    use \App\Http\Controllers\Api\Sections\Wallet\transferRequest\TransferRequestAction;
    use \App\Http\Controllers\Api\Sections\Users\unblockUser\UnblockUserAction;
    use \App\Http\Controllers\Api\Sections\FeaturedAds\unfeatureAd\UnfeatureAdAction;
    use \App\Http\Controllers\Api\Sections\Currency\updateCurrencyRate\UpdateCurrencyRateAction;
    use \App\Http\Controllers\Api\Sections\Items\updateItem\UpdateItemAction;
    use \App\Http\Controllers\Api\Sections\Items\updateItemStatus\UpdateItemStatusAction;
    use \App\Http\Controllers\Api\Sections\Services\updateOwnedService\UpdateOwnedServiceAction;
    use \App\Http\Controllers\Api\Sections\Auth\updatePassword\UpdatePasswordAction;
    use \App\Http\Controllers\Api\Sections\Chat\updatePresenceStatus\UpdatePresenceStatusAction;
    use \App\Http\Controllers\Api\Sections\Profile\updateProfile\UpdateProfileAction;
    use \App\Http\Controllers\Api\Sections\Chat\updateTypingStatus\UpdateTypingStatusAction;
    use \App\Http\Controllers\Api\Sections\Auth\userLogin\UserLoginAction;
    use \App\Http\Controllers\Api\Sections\Auth\userSignup\UserSignupAction;
    use \App\Http\Controllers\Api\Sections\Auth\verifyOtp\VerifyOtpAction;
    use \App\Http\Controllers\Api\Sections\Wallet\walletRecipient\WalletRecipientAction;
    use \App\Http\Controllers\Api\Sections\Wallet\walletRecipientLookup\WalletRecipientLookupAction;
    use \App\Http\Controllers\Api\Sections\Wallet\walletSummary\WalletSummaryAction;
    use \App\Http\Controllers\Api\Sections\Wallet\walletTransactions\WalletTransactionsAction;
    use \App\Http\Controllers\Api\Sections\Wallet\walletWithdrawalOptions\WalletWithdrawalOptionsAction;
    use \App\Http\Controllers\Api\Sections\Wallet\walletWithdrawalRequests\WalletWithdrawalRequestsAction;



    public static function interfaceTypes(bool $includeLegacy = false): array
    {
        $allowedSectionTypes = InterfaceSectionService::allowedSectionTypes(includeLegacy: $includeLegacy);
        $aliases = array_keys(InterfaceSectionService::sectionTypeAliases());

        if ($aliases !== []) {
            $allowedSectionTypes = array_merge($allowedSectionTypes, $aliases);
        }

        return array_values(array_unique(array_filter(
            $allowedSectionTypes,
            static fn ($type) => is_string($type) && $type !== ''
        )));
    }

    public const WALLET_TRANSACTION_FILTERS = [
        'all',
        'top-ups',
        'payments',
        'transfers',
        'refunds',
    ];

    /**
     * Cache of the available columns on the items table.
     */
    private static ?array $itemColumnAvailability = null;



    private const CURRENCY_SYNONYMS = [
        'yer' => 'YER',
        'أ¢â€¢ع¾أ¢â€“â€™أ¢â€‌ع©ط£آ¨أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍ أ¢â€‌ع©ط£آ¨أ¢â€‌ع©ط£آ أ¢â€‌ع©ط¢â€ أ¢â€‌ع©ط£آ¨' => 'YER',
        'أ¢â€¢ع¾أ¢â€“â€™أ¢â€‌ع©ط£آ¨أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍ أ¢â€‌ع©ط£آ¨أ¢â€‌ع©ط£آ أ¢â€‌ع©ط¢â€ أ¢â€‌ع©ط£آ«' => 'YER',
        'أ¢â€¢ع¾أ¢â€“â€™.أ¢â€‌ع©ط£آ¨' => 'YER',
        'أ¢â€¢ع¾أ¢â€“â€™. أ¢â€‌ع©ط£آ¨.' => 'YER',
        'sar' => 'SAR',
        'أ¢â€¢ع¾أ¢â€“â€™أ¢â€‌ع©ط£آ¨أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍ أ¢â€¢ع¾أ¢â€‌â€ڑأ¢â€¢ع¾أ¢â€¢آ£أ¢â€‌ع©ط£ع¾أ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط£آ¨' => 'SAR',
        'أ¢â€¢ع¾أ¢â€“â€™.أ¢â€¢ع¾أ¢â€‌â€ڑ' => 'SAR',
        'أ¢â€¢ع¾أ¢â€“â€™. أ¢â€¢ع¾أ¢â€‌â€ڑ.' => 'SAR',
        'sar أ¢â€¢ع¾أ¢â€“â€™.أ¢â€¢ع¾أ¢â€‌â€ڑ' => 'SAR',
        'omr' => 'OMR',
        'أ¢â€¢ع¾أ¢â€“â€™أ¢â€‌ع©ط£آ¨أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍ أ¢â€¢ع¾أ¢â€¢آ£أ¢â€‌ع©ط£آ أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€ أ¢â€‌ع©ط£آ¨' => 'OMR',
        'أ¢â€¢ع¾أ¢â€“â€™.أ¢â€¢ع¾أ¢â€¢آ£' => 'OMR',
        'aed' => 'AED',
        'أ¢â€¢ع¾ط¢آ»أ¢â€¢ع¾أ¢â€“â€™أ¢â€‌ع©ط£آ§أ¢â€‌ع©ط£آ  أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط£آ أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾أ¢â€“â€™أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾ط·آ²أ¢â€‌ع©ط£آ¨' => 'AED',
        'أ¢â€¢ع¾ط¢آ».أ¢â€¢ع¾ط·آ­' => 'AED',
        'kwd' => 'KWD',
        'أ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط£آ¨أ¢â€‌ع©ط¢â€ أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾أ¢â€“â€™ أ¢â€‌ع©ط£آ¢أ¢â€‌ع©ط£ع¾أ¢â€‌ع©ط£آ¨أ¢â€¢ع¾ط·آ²أ¢â€‌ع©ط£آ¨' => 'KWD',
        'أ¢â€¢ع¾ط¢آ».أ¢â€‌ع©ط£آ¢' => 'KWD',
        'bhd' => 'BHD',
        'أ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط£آ¨أ¢â€‌ع©ط¢â€ أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾أ¢â€“â€™ أ¢â€¢ع¾ط·آ°أ¢â€¢ع¾ط·آµأ¢â€¢ع¾أ¢â€“â€™أ¢â€‌ع©ط£آ¨أ¢â€‌ع©ط¢â€ أ¢â€‌ع©ط£آ¨' => 'BHD',
        'أ¢â€¢ع¾ط¢آ».أ¢â€¢ع¾ط·آ°' => 'BHD',
        'egp' => 'EGP',
        'أ¢â€¢ع¾ط·آ´أ¢â€‌ع©ط¢â€ أ¢â€‌ع©ط£آ¨أ¢â€‌ع©ط£آ§ أ¢â€‌ع©ط£آ أ¢â€¢ع¾أ¢â€¢طŒأ¢â€¢ع¾أ¢â€“â€™أ¢â€‌ع©ط£آ¨' => 'EGP',
        'أ¢â€¢ع¾ط·آ´.أ¢â€‌ع©ط£آ ' => 'EGP',
        'usd' => 'USD',
        'أ¢â€¢ع¾ط·آ«.أ¢â€¢ع¾أ¢â€“â€™' => 'USD',
        'أ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط£ع¾أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾أ¢â€“â€™' => 'USD',
        'أ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط£ع¾أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾أ¢â€“â€™ أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط£آ أ¢â€¢ع¾أ¢â€“â€™أ¢â€‌ع©ط£آ¨أ¢â€‌ع©ط£آ¢أ¢â€‌ع©ط£آ¨' => 'USD',
        '$' => 'USD',
        'eur' => 'EUR',
        'ط·آ¸ط£آ©ط·آ´' => 'EUR',
        'أ¢â€¢ع¾ط·آ´أ¢â€‌ع©ط¢â€ أ¢â€‌ع©ط£آ¨أ¢â€‌ع©ط£آ§ أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾أ¢â€‌â€ڑأ¢â€¢ع¾ط·آ²أ¢â€¢ع¾أ¢â€“â€™أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط£آ¨أ¢â€‌ع©ط¢â€ أ¢â€‌ع©ط£آ¨' => 'GBP',
        'gbp' => 'GBP',
        'أ¢â€‌آ¬ط·آ«' => 'GBP',
        'try' => 'TRY',
        'ط·آ¸ط£آ©أ¢â€¢â€ک' => 'TRY',
        'أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط£آ¨أ¢â€¢ع¾أ¢â€“â€™أ¢â€¢ع¾ط·آ± أ¢â€¢ع¾ط·آ²أ¢â€¢ع¾أ¢â€“â€™أ¢â€‌ع©ط£آ¢أ¢â€‌ع©ط£آ¨أ¢â€¢ع¾ط·آ±' => 'TRY',
    ];

    private function getWalletCurrencyCode(): string
    {
        return $this->walletService->getPrimaryCurrency();
    }

    private function ensureWalletAccountCurrency(WalletAccount $walletAccount): WalletAccount
    {
        $currency = $this->getWalletCurrencyCode();
        $current = Str::upper((string) $walletAccount->currency);

        if ($current === $currency) {
            return $walletAccount;
        }

        $existing = WalletAccount::query()
            ->where('user_id', $walletAccount->user_id)
            ->whereRaw('UPPER(currency) = ?', [$currency])
            ->first();

        if ($existing) {
            return $existing;
        }

        $walletAccount->forceFill(['currency' => $currency])->save();

        return $walletAccount->fresh();
    }


    protected function getWalletWithdrawalMethods(): array
    {
        $configuredMethods = config('wallet.withdrawals.methods', []);

        $methods = [];

        foreach ($configuredMethods as $method) {
            $key = (string) ($method['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $methods[$key] = [
                'key' => $key,
                'name' => __($method['name'] ?? Str::headline(str_replace('_', ' ', $key))),
                'description' => $method['description'] ?? null,
                
                'fields' => $this->normalizeWithdrawalMethodFields($method['fields'] ?? []),


            ];
        }

        return $methods;
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @return array<int, array{key: string, label: string, required: bool, rules: array<int, string>}>
     */
    private function normalizeWithdrawalMethodFields(array $fields): array
    {
        $normalized = [];

        foreach ($fields as $field) {
            $fieldKey = (string) ($field['key'] ?? '');

            if ($fieldKey === '') {
                continue;
            }

            $label = $field['label'] ?? Str::headline(str_replace('_', ' ', $fieldKey));
            $required = (bool) ($field['required'] ?? false);

            $rules = $field['rules'] ?? [];

            if (is_string($rules)) {
                $rules = array_filter(explode('|', $rules), static fn ($rule) => $rule !== '');
            }

            if (!is_array($rules)) {
                $rules = [];
            }

            $rules = array_values(array_map(static fn ($rule) => (string) $rule, $rules));

            if ($required && !$this->fieldRulesContainRequired($rules)) {
                array_unshift($rules, 'required');
            }

            if (!$required && empty($rules)) {
                $rules = ['nullable'];
            }

            $normalized[] = [
                'key' => $fieldKey,
                'label' => __($label),
                'required' => $required,
                'rules' => $rules,
            ];
        }

        return $normalized;
    }

    private function fieldRulesContainRequired(array $rules): bool
    {
        foreach ($rules as $rule) {
            if (!is_string($rule)) {
                continue;
            }

            if ($rule === 'required' || Str::startsWith($rule, 'required_')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function validateWithdrawalMeta(Request $request, array $method): ?array
    {
        $fields = $method['fields'] ?? [];

        if (empty($fields)) {
            return null;
        }

        $metaRules = [];
        $attributeNames = [];

        foreach ($fields as $field) {
            $fieldKey = $field['key'];
            $rules = $field['rules'] ?? [];

            if (empty($rules)) {
                $rules = $field['required'] ? ['required'] : [];
            }

            $metaRules[$fieldKey] = $rules;
            $attributeNames[$fieldKey] = $field['label'] ?? Str::headline(str_replace('_', ' ', $fieldKey));
        }

        $metaData = $request->input('meta', []);

        if (!is_array($metaData)) {
            $metaData = [];
        }

        $metaValidator = Validator::make($metaData, $metaRules, [], $attributeNames);

        if ($metaValidator->fails()) {
            ResponseService::validationError($metaValidator->errors()->first());
        }

        $validatedMeta = $metaValidator->validated();

        $sanitizedMeta = [];

        foreach ($fields as $field) {
            $fieldKey = $field['key'];

            if (array_key_exists($fieldKey, $validatedMeta)) {
                $sanitizedMeta[$fieldKey] = $validatedMeta[$fieldKey];
            }
        }

        return $sanitizedMeta === [] ? null : $sanitizedMeta;
    }


    private string $uploadFolder;
    private array $departmentCategoryMap = [];
    private ?array $geoDisabledCategoryCache = null;
    private ?array $productLinkRequiredCategoryCache = null;
    private ?array $productLinkRequiredSectionCache = null;
    private ?array $interfaceSectionCategoryCache = null;



    public function __construct(
        private DelegateAuthorizationService $delegateAuthorizationService,
        private DepartmentReportService $departmentReportService,
        private ServiceAuthorizationService $serviceAuthorizationService,
        private PaymentFulfillmentService $paymentFulfillmentService,

        private WalletService $walletService,
        private MaribBoundaryService $maribBoundaryService,
        private ReferralAuditLogger $referralAuditLogger,
        private DelegateNotificationService $delegateNotificationService


    ) {
        
        
        $this->uploadFolder = 'item_images';
        if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $this->middleware('auth:sanctum');
        }
    }


    private function formatReferralAttempt(ReferralAttempt $attempt): array
    {
        return array_filter([
            'id' => $attempt->id,
            'code' => $attempt->code,
            'status' => $attempt->status,
            'referrer_id' => $attempt->referrer_id,
            'referred_user_id' => $attempt->referred_user_id,
            'referral_id' => $attempt->referral_id,
            'challenge_id' => $attempt->challenge_id,
            'awarded_points' => $attempt->awarded_points,
            'lat' => $attempt->lat,
            'lng' => $attempt->lng,
            'admin_area' => $attempt->admin_area,
            'device_time' => $attempt->device_time,
            'contact' => $attempt->contact,
            'request_ip' => $attempt->request_ip,
            'user_agent' => $attempt->user_agent,
            'exception_message' => $attempt->exception_message,
            'meta' => $attempt->meta,
            'created_at' => $attempt->created_at?->toIso8601String(),
        ], static fn ($value) => $value !== null && $value !== '');
    }



    private function resolvePerPage(Request $request, int $default = 15, int $max = 100): int
    {
        $perPage = $request->integer('per_page', $default) ?? $default;

        if ($perPage <= 0) {
            $perPage = $default;
        }


        return min($perPage, $max);
    }



    private function requestHasBoundingBox(Request $request): bool
    {
        return $request->filled('sw_lat')
            && $request->filled('sw_lng')
            && $request->filled('ne_lat')
            && $request->filled('ne_lng');
    }

    private function applyBoundingBoxFilter(Builder $sql, Request $request): Builder
    {
        $swLat = (float) $request->query('sw_lat');
        $neLat = (float) $request->query('ne_lat');
        $swLng = (float) $request->query('sw_lng');
        $neLng = (float) $request->query('ne_lng');

        $minLat = min($swLat, $neLat);
        $maxLat = max($swLat, $neLat);

        $sql->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$minLat, $maxLat])
            ->where(function (Builder $query) use ($swLng, $neLng): void {
                if ($swLng <= $neLng) {
                    $minLng = min($swLng, $neLng);
                    $maxLng = max($swLng, $neLng);

                    $query->whereBetween('longitude', [$minLng, $maxLng]);

                    return;
                }

                $query->where(function (Builder $wrap) use ($swLng, $neLng): void {
                    $wrap->whereBetween('longitude', [$swLng, 180])
                        ->orWhereBetween('longitude', [-180, $neLng]);
                });
            });

        return $sql;
    }









    /**
     * @return array<int, string>
     */
    private function normalizeSettingNames(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        $names = [];

        foreach ($value as $entry) {
            if (is_string($entry)) {
                $candidate = trim($entry);
            } elseif (is_numeric($entry)) {
                $candidate = (string) $entry;
            } else {
                continue;

            }
            if ($candidate !== '') {
                $names[] = $candidate;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return array<int, string>
     */
    private function socialLinkSettingKeys(): array
    {
        $meta = config('constants.SOCIAL_LINKS_META', []);




        $keys = [];

        foreach ($meta as $key => $definition) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $keys[] = $key;

            $enabledKey = $definition['enabled_key'] ?? null;

            if (is_string($enabledKey) && $enabledKey !== '') {
                $keys[] = $enabledKey;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param array<int, string> $keys
     * @param array<string, mixed> $seed
     * @return array<string, mixed>
     */
    private function hydrateSettingValues(array $keys, array $seed): array
    {
        if (empty($keys)) {
            return $seed;
        }

        $missing = array_values(array_diff($keys, array_keys($seed)));

        if (!empty($missing)) {
            $additional = Setting::query()
                ->select(['name', 'value'])
                ->whereIn('name', $missing)
                ->pluck('value', 'name')
                ->all();

            foreach ($additional as $name => $value) {
                if (is_string($name) && $name !== '' && !array_key_exists($name, $seed)) {
                    $seed[$name] = $value;
                }
            }
        }


        return $seed;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<int, array{key: string, label: string, icon: mixed, url: string, department: mixed}>
     */
    private function buildSocialLinks(array $settings): array
    {
        $socialLinksMeta = config('constants.SOCIAL_LINKS_META', []);
        $socialLinks = [];


        foreach ($socialLinksMeta as $key => $meta) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $value = $settings[$key] ?? null;


            $enabledKey = $meta['enabled_key'] ?? null;


              if (is_string($enabledKey) && $enabledKey !== '') {
                $enabledValue = $settings[$enabledKey] ?? null;
                if (!filter_var($enabledValue, FILTER_VALIDATE_BOOLEAN)) {
                    continue;
                }

            }

            if (blank($value)) {
                continue;
            }

            $isWhatsapp = ($meta['type'] ?? null) === 'whatsapp';
            $url = $value;

            if ($isWhatsapp) {
                $normalizedNumber = preg_replace('/[^0-9]/', '', (string) $value) ?? '';


                if ($normalizedNumber === '') {

                    continue;
                }

                $url = 'https://wa.me/' . $normalizedNumber;
            }

            $socialLinks[] = [
                'key'        => $key,
                'label'      => $meta['label'] ?? Str::title(str_replace('_', ' ', $key)),
                'icon'       => $meta['icon'] ?? null,
                'url'        => $url,
                'department' => $meta['department'] ?? null,
            ];
        }

        return $socialLinks;
    }



    


    


    




    


    private function handleDeferredPhoneSignup(Request $request, string $firebaseId)
    {
        $mobile = $request->mobile ?? '';
        $normalizedMobile = $this->normalizePhoneNumber($request->country_code, $mobile);

        $existingVerifiedUser = User::where('mobile', $mobile)
            ->where(function ($query) {
                $query->where('is_verified', 1)
                    ->orWhereNotNull('email_verified_at');
            })
            ->first();

        if ($existingVerifiedUser) {
            ResponseService::errorResponse('هذا الرقم مسجل بالفعل، يرجى تسجيل الدخول');
        }

        $payload = $this->buildPendingSignupPayload($request, $firebaseId, $normalizedMobile);

        $pendingSignup = PendingSignup::updateOrCreate(
            ['normalized_mobile' => $normalizedMobile],
            [
                'mobile' => $mobile,
                'country_code' => $request->country_code,
                'firebase_id' => $firebaseId,
                'type' => 'phone',
                'payload' => json_encode($payload),
                'expires_at' => now()->addMinutes(30),
            ]
        );

        ResponseService::successResponse(
            'سيتم إنشاء الحساب بعد التحقق من الرمز',
            [
                'pending_signup_id' => $pendingSignup->id,
                'mobile' => $mobile,
                'country_code' => $request->country_code,
            ],
            [
                'pending_signup' => [
                    'id' => $pendingSignup->id,
                    'mobile' => $mobile,
                    'country_code' => $request->country_code,
                ],
                'token' => null,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPendingSignupPayload(Request $request, string $firebaseId, string $normalizedMobile): array
    {
        $accountType = (int) ($request->account_type ?? User::ACCOUNT_TYPE_CUSTOMER);
        $password = $request->password ?? Str::random(12);

        $email = $request->email;
        if (!is_string($email) || trim($email) === '') {
            $email = $this->generatePhoneSignupEmail(
                $request->country_code,
                $request->mobile
            );
        } else {
            $email = Str::lower(trim($email));
        }

        $name = $request->name;
        if (!is_string($name) || trim($name) === '') {
            $name = $request->mobile ?? 'User';
        }

        $userData = [
            'name' => $name,
            'mobile' => $request->mobile,
            'email' => $email,
            'password' => Hash::make($password),
            'account_type' => $accountType,
            'country_code' => $request->country_code,
            'country_name' => $request->country_name,
            'flag_emoji' => $request->flag_emoji ?? 'ye',
            'firebase_id' => $firebaseId,
            'fcm_id' => (string) ($request->fcm_id ?? ''),
            'type' => 'phone',
            'platform_type' => $request->platform_type,
            'is_verified' => 0,
            'email_verified_at' => null,
            'normalized_mobile' => $normalizedMobile,
        ];

        if (!is_string($userData['fcm_id'])) {
            $userData['fcm_id'] = '';
        }

        if ($accountType === User::ACCOUNT_TYPE_SELLER) {
            $userData['name'] = $this->fallbackSellerName($request, $userData);
        }

        $referralPayload = [
            'code' => $request->code,
            'contact' => $request->mobile ?? $request->email,
            'location' => $this->buildReferralLocationPayload($request),
            'meta' => $this->buildReferralRequestMeta($request),
        ];

        return [
            'user' => $userData,
            'referral' => $referralPayload,
        ];
    }

    private function normalizePhoneNumber(?string $countryCode, ?string $mobile): string
    {
        $mobileDigits = preg_replace('/\D+/', '', (string) ($mobile ?? ''));
        $codeDigits = preg_replace('/\D+/', '', (string) ($countryCode ?? ''));

        if ($mobileDigits === '' && $codeDigits === '') {
            return '';
        }

        if ($codeDigits !== '' && strncmp($mobileDigits, $codeDigits, strlen($codeDigits)) === 0) {
            return $mobileDigits;
        }

        return $codeDigits . $mobileDigits;
    }

    private function finalizePendingSignup(PendingSignup $pendingSignup)
    {
        $payload = $pendingSignup->payloadAsArray();
        $userData = $payload['user'] ?? [];

        if (empty($userData)) {
            $pendingSignup->delete();
            ResponseService::errorResponse('���� ������� �������. ���� �������� ��� ����.');
        }

        unset($userData['normalized_mobile']);
        $userData['is_verified'] = 1;
        $userData['email_verified_at'] = now();

        DB::beginTransaction();
        try {
            $existingUser = null;

            if (!empty($userData['mobile'])) {
                $existingUser = User::where('mobile', $userData['mobile'])->first();
            }

            if (!$existingUser && !empty($userData['email'])) {
                $existingUser = User::where('email', $userData['email'])->first();
            }

            if ($existingUser) {
                if ((int) $existingUser->is_verified === 1 || !empty($existingUser->email_verified_at)) {
                    ResponseService::errorResponse('Account already exists.', null, 409);
                }

                $existingUser->fill($userData);
                $existingUser->is_verified = 1;
                $existingUser->email_verified_at = now();
                $existingUser->save();

                $user = $existingUser;
            } else {
                $user = User::create($userData);
            }
            if (!$user->hasRole('User')) {
                $user->assignRole('User');
            }

            Auth::guard('web')->login($user);
            $auth = User::find($user->id);

            $referral = $payload['referral'] ?? [];
            if (!empty($referral['code'])) {
                $this->handleReferralCode(
                    $referral['code'],
                    $auth,
                    $referral['contact'] ?? $auth->mobile ?? $auth->email,
                    $referral['location'] ?? [],
                    $referral['meta'] ?? []
                );
            }

            $pendingSignup->delete();

            $token = $auth->createToken($auth->name ?? '')->plainTextToken;

            DB::commit();

            ResponseService::successResponse('�� ����� ������ �����.', $auth, ['token' => $token]);
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, 'API Controller -> finalizePendingSignup');
            ResponseService::errorResponse();
        }
    }

    


    


    





     

     
     private function buildStorePolicySummary($store): ?string
     {
        if ($store === null) {
            return null;
        }

        $policies = $store->relationLoaded('policies')
            ? $store->policies
            : $store->policies()->where('is_active', true)->get();

        if ($policies === null) {
            return null;
        }

        $lines = $policies->filter(static function ($policy) {
            return (bool) $policy->is_active && trim((string) $policy->content) !== '';
        })->sortBy(static function ($policy) {
            return $policy->display_order ?? 0;
        })->map(static function ($policy) {
            $title = trim((string) ($policy->title ?? ''));
            $content = trim((string) $policy->content);
            if ($content === '') {
                return null;
            }
            return $title !== '' ? "{$title}: {$content}" : $content;
        })->filter()->values();

        if ($lines->isEmpty()) {
            return null;
        }

        return $lines->map(static fn ($line) => 'ط·آ¸ط¢â‚¬ط·ع¾ ' . $line)->implode("\n");
     }




     



    
 catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getItem");
            ResponseService::errorResponse();
        }
    }


    /**
     * @param  AbstractPaginator|Collection  $result
     */
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


    



    
    


    


    


    


    


    


    


    



    


    


    


    


    



    


    


    


    

    





    



    protected function resolveSliderSessionId(Request $request): ?string
    {
        if ($request->hasSession()) {
            return $request->session()->getId();
        }

        return $request->header('X-Session-Id')
            ?? $request->cookie('slider_session')
            ?? $request->ip();
    }


    





    

    


    


    


    





    


    





    


    



    



    


    





    



    



    


    


    


    





   private function resolveLegacyLastMessageTimes(Collection $offerIds): Collection
    {
        if ($offerIds->isEmpty() || !Schema::hasTable('chats')) {
            return collect();
        }
    
        return DB::table('chats')
            ->whereIn('item_offer_id', $offerIds)
            ->select('item_offer_id', DB::raw('MAX(updated_at) as last_message_time'))
            ->groupBy('item_offer_id')
            ->pluck('last_message_time', 'item_offer_id');
    }
    
    private function enrichOfferWithChatData(
        ItemOffer $offer,
        User $user,
        Collection $authUserBlockList,
        Collection $otherUserBlockList,
        Collection $legacyLastMessageTimes,
        ?string $type = null,
        ?Chat $conversation = null
    ): ItemOffer {
        $type = $type ?: ($offer->seller_id === $user->id ? 'seller' : 'buyer');
    
        $userBlocked = false;
        if ($type === 'seller') {
            $userBlocked = $authUserBlockList->contains($offer->buyer_id)
                || $otherUserBlockList->contains($offer->seller_id);
        } else {
            $userBlocked = $authUserBlockList->contains($offer->seller_id)
                || $otherUserBlockList->contains($offer->buyer_id);
        }
    
        $offer->setAttribute('user_blocked', (bool) $userBlocked);
    
        $item = $offer->item;
        if ($item) {
            $item->is_purchased = 0;
            if ($item->sold_to == $user->id) {
                $item->is_purchased = 1;
            }
            $tempReview = $item->review;
            unset($item->review);
            $item->review = $tempReview[0] ?? null;
            $offer->setRelation('item', $item);
        }
    
        $chat = $conversation ?: $offer->chat;
    
        $needsHydration = false;
        if (!$chat) {
            $needsHydration = true;
        } elseif ($chat->relationLoaded('messages')) {
            $needsHydration = $chat->messages->isEmpty();
        } elseif (!$chat->messages()->exists()) {
            $needsHydration = true;
        }
    
        if ($needsHydration && $legacyLastMessageTimes->has($offer->id)) {
            $chat = $this->hydrateLegacyChatConversation($offer, $chat);
        }
    
        if ($chat) {
            $chat->loadMissing([
                'participants' => function ($participantQuery) {
                    $participantQuery->withTrashed()->select('users.id', 'users.name', 'users.profile');
                },
                'latestMessage' => function ($messageQuery) {
                    $messageQuery->with(['sender:id,name,profile', 'conversation:id,item_offer_id']);
                },
            ]);
        }
    
        $offer->setRelation('chat', $chat);
    
        $lastMessageTime = optional($chat)->updated_at;
        if (empty($lastMessageTime) && $legacyLastMessageTimes->has($offer->id)) {
            $legacyTime = $legacyLastMessageTimes->get($offer->id);
            $lastMessageTime = $legacyTime ? Carbon::parse($legacyTime) : null;
        }
    
        $offer->setAttribute('conversation_id', $chat?->id);
        $offer->setAttribute('last_message_time', $lastMessageTime ? $lastMessageTime->toDateTimeString() : null);
        $offer->setAttribute(
            'participants',
            $this->buildParticipantsPayload($offer, $chat, $user, $authUserBlockList, $otherUserBlockList)
        );
        $offer->setAttribute('last_message', $chat?->latestMessage ? $chat->latestMessage->toArray() : null);
    
        $unread = 0;
        if ($chat) {
            if (isset($chat->unread_messages_count)) {
                $unread = (int) $chat->unread_messages_count;
            } else {
                $unread = $chat->messages()
                    ->whereNull('read_at')
                    ->where(function ($query) use ($user) {
                        $query->whereNull('sender_id')
                            ->orWhere('sender_id', '!=', $user->id);
                    })->count();
            }
        }
        $offer->setAttribute('unread_messages_count', $unread);
    
        return $offer;
    }
    
    private function buildParticipantsPayload(
        ItemOffer $offer,
        ?Chat $conversation,
        User $user,
        Collection $authUserBlockList,
        Collection $otherUserBlockList
    ): array {
        $participants = collect();
    
        if ($conversation && $conversation->relationLoaded('participants')) {
            $participants = $conversation->participants->map(function (User $participant) use (
                $offer,
                $authUserBlockList,
                $otherUserBlockList
            ) {
                $role = $participant->id === $offer->seller_id
                    ? 'seller'
                    : ($participant->id === $offer->buyer_id ? 'buyer' : 'participant');
    
                $status = [
                    'is_online' => (bool) $participant->pivot->is_online,
                    'is_typing' => (bool) $participant->pivot->is_typing,
                    'last_seen' => optional($participant->pivot->last_seen_at)->toIso8601String(),
                    'last_typing_at' => optional($participant->pivot->last_typing_at)->toIso8601String(),
                    'is_blocked' => $authUserBlockList->contains($participant->id)
                        || $otherUserBlockList->contains($participant->id),
                ];
    
                return [
                    'user_id' => $participant->id,
                    'id' => $participant->id,
                    'name' => $participant->name,
                    'profile' => $participant->profile,
                    'role' => $role,
                    'status' => array_filter($status, function ($value) {
                        return $value !== null && $value !== '';
                    }),
                ];
            });
        }
    
        if ($participants->isEmpty()) {
            $fallback = collect();
    
            if ($offer->seller) {
                $fallback->push([
                    'user_id' => $offer->seller->id,
                    'id' => $offer->seller->id,
                    'name' => $offer->seller->name,
                    'profile' => $offer->seller->profile,
                    'role' => 'seller',
                    'status' => [
                        'is_online' => false,
                        'is_typing' => false,
                        'last_seen' => null,
                        'is_blocked' => $authUserBlockList->contains($offer->seller->id)
                            || $otherUserBlockList->contains($offer->seller->id),
                    ],
                ]);
            }
    
            if ($offer->buyer) {
                $fallback->push([
                    'user_id' => $offer->buyer->id,
                    'id' => $offer->buyer->id,
                    'name' => $offer->buyer->name,
                    'profile' => $offer->buyer->profile,
                    'role' => 'buyer',
                    'status' => [
                        'is_online' => false,
                        'is_typing' => false,
                        'last_seen' => null,
                        'is_blocked' => $authUserBlockList->contains($offer->buyer->id)
                            || $otherUserBlockList->contains($offer->buyer->id),
                    ],
                ]);
            }
    
            $participants = $fallback;
        }
    
        return $participants->values()->toArray();
    }


    private function hydrateLegacyChatConversation(ItemOffer $itemOffer, ?Chat $conversation = null): ?Chat
    {
        if (!Schema::hasTable('chats')) {
            return $conversation;
        }

        if ($conversation && $conversation->messages()->exists()) {
            return $conversation;
        }

        $legacyRows = DB::table('chats')
            ->where('item_offer_id', $itemOffer->id)
            ->orderBy('id')
            ->get();

        if ($legacyRows->isEmpty()) {
            return $conversation;
        }

        return DB::transaction(function () use ($legacyRows, $itemOffer, $conversation) {
            $conversationAttributes = [];
            $resolvedDepartment = $this->resolveSectionByCategoryId($itemOffer->item?->category_id);

            if ($resolvedDepartment !== null && $this->chatConversationsSupportsColumn('department')) {
                $conversationAttributes['department'] = $resolvedDepartment;
            }

            $conversation = $conversation ?: Chat::firstOrCreate(
                ['item_offer_id' => $itemOffer->id],
                $conversationAttributes
            );

            if ($conversation->messages()->exists()) {
                return $conversation;
            }

            $participantIds = collect([$itemOffer->seller_id, $itemOffer->buyer_id]);

            $messagesToInsert = [];

            foreach ($legacyRows as $row) {
                if (!empty($row->sender_id)) {
                    $participantIds->push($row->sender_id);
                }

                if (isset($row->receiver_id) && !empty($row->receiver_id)) {
                    $participantIds->push($row->receiver_id);
                }

                if (empty($row->sender_id)) {
                    continue;
                }

                $rowCreatedAt = !empty($row->created_at) ? Carbon::parse($row->created_at) : Carbon::now();
                $rowUpdatedAt = !empty($row->updated_at) ? Carbon::parse($row->updated_at) : $rowCreatedAt;

                $messageContent = $row->message === '' ? null : $row->message;

                $messagesToInsert[] = [
                    'conversation_id' => $conversation->id,
                    'sender_id' => $row->sender_id,
                    'message' => $messageContent,
                    'file' => $row->file ?: null,
                    'audio' => $row->audio ?: null,
                    'status' => ChatMessage::STATUS_SENT,
                    'created_at' => $rowCreatedAt->toDateTimeString(),
                    'updated_at' => $rowUpdatedAt->toDateTimeString(),
                ];
            }

            if (!empty($messagesToInsert)) {
                DB::table('chat_messages')->insert($messagesToInsert);
            }

            $uniqueParticipants = $participantIds->filter()->unique()->values();

            if ($uniqueParticipants->isNotEmpty()) {
                $conversation->participants()->syncWithoutDetaching($uniqueParticipants->all());
            }

            $createdAt = $legacyRows->pluck('created_at')
                ->filter()
                ->map(fn ($value) => Carbon::parse($value))
                ->min() ?? Carbon::now();

            $updatedAt = $legacyRows->pluck('updated_at')
                ->filter()
                ->map(fn ($value) => Carbon::parse($value))
                ->max() ?? $createdAt;

            DB::table('chat_conversations')
                ->where('id', $conversation->id)
                ->update([
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ]);

            $conversation->created_at = $createdAt;
            $conversation->updated_at = $updatedAt;

            return $conversation;
        });
    }



    


    



    


    




    


    


    


    


    






    private function applyWalletTransactionFilter(Builder $query, string $filter): void
    {
        switch ($filter) {
            case 'top-ups':
                $query->where('type', 'credit')
                    ->where(function (Builder $builder) {
                        $builder->whereNotNull('manual_payment_request_id')
                            ->orWhere('meta->reason', ManualPaymentRequest::PAYABLE_TYPE_WALLET_TOP_UP)
                            ->orWhere('meta->reason', 'wallet_top_up')
                            ->orWhere('meta->reason', 'admin_manual_credit');
                        
                        });
                break;
            case 'payments':
                $query->where('type', 'debit')
                    ->where(function (Builder $builder) {
                        $builder->whereNull('meta->reason')
                            ->orWhere('meta->reason', '!=', 'wallet_transfer');
                    });
                break;
            case 'transfers':
                $query->where(function (Builder $builder) {
                    $builder->where('meta->reason', 'wallet_transfer')
                        ->orWhere('meta->context', 'wallet_transfer');
                });


                break;
            case 'refunds':
                $query->where('type', 'credit')
                    ->where(function (Builder $builder) {
                        $builder->where('meta->reason', 'refund')
                            ->orWhere('meta->reason', 'wallet_refund');
                    });
                break;
            default:
                break;
        }
    }

    /**
     * @return array{available: array<int, array<string, string>>, applied?: string, default?: string}
     */
    private function buildWalletFilterPayload(?string $applied = null, bool $includeDefault = false): array
    {
        $available = array_map(function (string $value) {
            return [
                'value' => $value,
                'label' => $this->walletFilterLabel($value),
            ];
        }, self::WALLET_TRANSACTION_FILTERS);

        $payload = [
            'available' => $available,
        ];

        if ($applied !== null) {
            $payload['applied'] = $applied;
        }

        if ($includeDefault) {
            $payload['default'] = 'all';
        }

        return $payload;
    }

    private function walletFilterLabel(string $filter): string
    {
        return match ($filter) {
            'top-ups' => __('wallet.filters.top_ups'),
            'payments' => __('wallet.filters.payments'),
            'transfers' => __('wallet.filters.transfers'),
            'refunds' => __('wallet.filters.refunds'),
            default => __('wallet.filters.all'),
        };
    }


    private function performWalletTransfer(
        User $sender,
        User $recipient,
        float $amount,
        string $idempotencyKey,
        string $clientTag,
        ?string $currency = null,
        ?string $reference = null,
        ?string $notes = null
    ): array {
        return DB::transaction(function () use ($sender, $recipient, $amount, $idempotencyKey, $clientTag, $currency, $reference, $notes) {
            $debitKey = $this->buildDirectionalWalletTransferKey($idempotencyKey, 'debit');
            $creditKey = $this->buildDirectionalWalletTransferKey($idempotencyKey, 'credit');

            $existingDebit = WalletTransaction::query()
                ->where('idempotency_key', $debitKey)
                ->whereHas('account', static function ($query) use ($sender) {
                    $query->where('user_id', $sender->id);
                })
                ->lockForUpdate()
                ->first();

            $existingCredit = WalletTransaction::query()
                ->where('idempotency_key', $creditKey)
                ->whereHas('account', static function ($query) use ($recipient) {
                    $query->where('user_id', $recipient->id);
                })
                ->lockForUpdate()
                ->first();

            if ($existingDebit && $existingCredit) {
                return [$existingDebit->fresh(), $existingCredit->fresh(), true];
            }

            if (($existingDebit && !$existingCredit) || (!$existingDebit && $existingCredit)) {
                throw new RuntimeException('Wallet transfer is in an inconsistent state.');
            }

            $debitMeta = $this->buildWalletTransferMeta('outgoing', $idempotencyKey, $clientTag, $reference, $notes, $recipient);
            $creditMeta = $this->buildWalletTransferMeta('incoming', $idempotencyKey, $clientTag, $reference, $notes, $sender);

            $debitOptions = [
                'meta' => $debitMeta,
            ];

            if ($currency !== null) {
                $debitOptions['currency'] = $currency;
            }

            $debitTransaction = $this->walletService->debit($sender, $debitKey, $amount, $debitOptions);

            $creditOptions = [

                'meta' => $creditMeta,
            ];

            if ($currency !== null) {
                $creditOptions['currency'] = $currency;
            }

            $creditTransaction = $this->walletService->credit($recipient, $creditKey, $amount, $creditOptions);

            return [$debitTransaction, $creditTransaction, false];
        });
    }



    private function maskMobileNumber(?string $mobile): ?string
    {
        if ($mobile === null || $mobile === '') {
            return null;
        }

        $characters = preg_split('//u', $mobile, -1, PREG_SPLIT_NO_EMPTY);

        if ($characters === false || $characters === null) {
            return null;
        }

        $digitCount = 0;
        foreach ($characters as $character) {
            if (ctype_digit($character)) {
                $digitCount++;
            }
        }

        if ($digitCount <= 3) {
            return $mobile;
        }

        $digitsToMask = $digitCount - 3;
        $masked = 0;

        foreach ($characters as $index => $character) {
            if (!ctype_digit($character)) {
                continue;
            }

            if ($masked < $digitsToMask) {
                $characters[$index] = '*';
                $masked++;
            }
        }

        return implode('', $characters);
    }


    private function buildWalletTransferMeta(
        string $direction,
        string $transferKey,
        string $clientTag,
        ?string $reference,
        ?string $notes,
        User $counterparty
    ): array {
        $meta = [
            'context' => 'wallet_transfer',
            'direction' => $direction,
            'transfer_key' => $transferKey,
            'client_tag' => $clientTag,
            'reason' => 'wallet_transfer',
            'counterparty' => [
                'id' => $counterparty->id,
                'name' => $counterparty->name,
            ],
        ];

        if ($reference !== null && $reference !== '') {
            $meta['reference'] = $reference;
        }

        if ($notes !== null && $notes !== '') {
            $meta['notes'] = $notes;
        }

        return $meta;
    }

    private function buildDirectionalWalletTransferKey(string $baseKey, string $direction): string
    {
        return sprintf('%s:%s', $baseKey, $direction);
    }

    private function buildWalletTransferIdempotencyKey(User $sender, User $recipient, float $amount, string $clientTag): string
    {
        $normalizedAmount = number_format($amount, 2, '.', '');

        return sprintf(
            'wallet_transfer:%d:%d:%s:%s',
            $sender->id,
            $recipient->id,
            $normalizedAmount,
            md5($clientTag)
        );
    }


    private function normalizeCurrencyCode(?string $currency): ?string
    {
        if ($currency === null) {
            return null;
        }

        $trimmed = trim($currency);

        if ($trimmed === '') {
            return null;
        }

        $lower = Str::lower($trimmed);

        if (isset(self::CURRENCY_SYNONYMS[$lower])) {
            return self::CURRENCY_SYNONYMS[$lower];
        }

        $tokens = preg_split('/[\s\-_/\\()]+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($tokens as $token) {
            $tokenLower = Str::lower($token);

            if (isset(self::CURRENCY_SYNONYMS[$tokenLower])) {
                return self::CURRENCY_SYNONYMS[$tokenLower];
            }
        }

        if (preg_match('/[A-Z]{3}/i', strtoupper($trimmed), $matches) === 1) {
            return strtoupper($matches[0]);
        }

        return null;
    }




    private function buildWalletIdempotencyKey(string $context, int $userId, int|string $subjectId): string
    {
        return sprintf('wallet:%s:%d:%s', $context, $userId, $subjectId);
    }

    private function buildManualPaymentWalletIdempotencyKey(User $user, ?string $payableType, ?int $payableId, float $amount, ?string $currency = null): string
    {
        if (is_string($payableType) && class_exists($payableType)) {
            $normalizedType = Str::of($payableType)->lower()->replace('\\', '_')->toString();
        } elseif (is_string($payableType)) {
            $normalizedType = Str::of($payableType)->lower()->toString();
        } else {
            $normalizedType = 'none';
        }

        $subjectParts = [
            $normalizedType,
            $payableId !== null ? (string) $payableId : 'none',
            number_format($amount, 2, '.', ''),
        ];

        if ($currency) {
            $subjectParts[] = strtoupper($currency);
        }

        return $this->buildWalletIdempotencyKey('manual_payment', $user->id, implode(':', $subjectParts));
    }

    private function findWalletPaymentTransaction(int $userId, ?string $payableType, ?int $payableId, string $idempotencyKey): ?PaymentTransaction


    {
        return PaymentTransaction::query()
            ->where('user_id', $userId)
            ->where('payment_gateway', 'wallet')
            ->where('order_id', $idempotencyKey)
            ->when($payableType !== null, static function ($query) use ($payableType) {
                $query->where('payable_type', $payableType);
            }, static function ($query) {
                $query->whereNull('payable_type');
            })
            ->when($payableId !== null, static function ($query) use ($payableId) {
                $query->where('payable_id', $payableId);
            }, static function ($query) {
                $query->whereNull('payable_id');
            })


            ->lockForUpdate()
            ->first();
    }

    private function findOrCreateWalletTransaction(?PaymentTransaction $existingTransaction, int $userId, Package $package, string $idempotencyKey, string $purchaseToken): PaymentTransaction
    {
        if ($existingTransaction) {
            $existingTransaction->forceFill([
                'amount' => $package->final_price,
            ])->save();

            return $existingTransaction->fresh();
        }

        return PaymentTransaction::create([
            'user_id' => $userId,
            'amount' => $package->final_price,
            'payment_gateway' => 'wallet',
            'order_id' => $idempotencyKey,
            'payment_status' => 'pending',
            'payable_type' => Package::class,
            'payable_id' => $package->id,
            'meta' => [
                'wallet' => [
                    'idempotency_key' => $idempotencyKey,
                    'purchase_token' => $purchaseToken,
                ],
            ],
        ]);
    }

    private function ensureWalletDebit(PaymentTransaction $transaction, User $user, Package $package, string $idempotencyKey): WalletTransaction


    {
        return $this->debitWalletTransaction($transaction, $user, $idempotencyKey, (float) $package->final_price, [
            'meta' => [
                'context' => 'package_purchase',
                'package_id' => $package->id,
            ],
        ]);
    }

    private function debitWalletTransaction(PaymentTransaction $transaction, User $user, string $idempotencyKey, float $amount, array $options = []): WalletTransaction


    {
        $walletTransactionId = data_get($transaction->meta, 'wallet.transaction_id');

        if ($walletTransactionId) {
            $walletTransaction = WalletTransaction::query()
                ->whereKey($walletTransactionId)
                ->lockForUpdate()
                ->first();

            if ($walletTransaction) {
                return $walletTransaction;
            }
        }

        try {
            return $this->walletService->debit($user, $idempotencyKey, $amount, array_merge([
                'payment_transaction' => $transaction,

            ], $options));

        } catch (RuntimeException $runtimeException) {
            if (str_contains(strtolower($runtimeException->getMessage()), 'insufficient wallet balance')) {
                DB::rollBack();
                ResponseService::errorResponse('Insufficient wallet balance');
            }

            $walletTransaction = $this->resolveWalletTransaction($user, $idempotencyKey);

            if (!$walletTransaction) {
                throw $runtimeException;
            }

            return $walletTransaction;
        }
    }

    private function resolveWalletTransaction(User $user, string $idempotencyKey): ?WalletTransaction
    {
        return WalletTransaction::query()
            ->where('idempotency_key', $idempotencyKey)
            ->whereHas('account', static function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->lockForUpdate()
            ->first();
    }



    


    


    


    


    


    


    


    


    


    



    


    


    



        


    


    



    


    protected function userCanReviewService(User $user, Service $service): bool
    {
        if ((int) $service->direct_user_id === (int) $user->id && $service->direct_to_user) {
            return true;
        }

        if (Schema::hasTable('service_requests')) {
            $hasQualifiedRequest = ServiceRequest::where('service_id', $service->id)
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->where(function ($query) {
                    $query->whereNull('status')
                        ->orWhereIn('status', ['approved', 'review', 'completed', 'closed']);
                })
                ->exists();

            if ($hasQualifiedRequest) {
                return true;
            }
        }

        return false;
    }



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

    


    


    





    protected function serializeVerificationField(VerificationField $field): array
    {
        return [
            'id' => $field->id,
            'name' => $field->name,
            'type' => $field->type,
            'account_type' => $field->account_type ?? 'individual',
            'required' => $field->is_required ? 1 : 0,
            'min_length' => $field->min_length,
            'max_length' => $field->max_length,
            'status' => $field->deleted_at ? 0 : 1,
            'values' => is_array($field->values)
                ? $field->values
                : (empty($field->values) ? [] : [(string) $field->values]),
        ];
    }

    protected function buildVerificationBenefits(string $accountType, ?VerificationPlan $plan): array
    {
        $baseBenefits = [
            'individual' => [
                'شارة موثقة أمام اسمك',
                'ثقة أعلى لدى المشترين',
                'أولوية في البحث والإعلانات',
            ],
            'commercial' => [
                'شارات توثيق للمتاجر والشركات',
                'تعزيز ظهور المنتجات والخدمات',
                'إمكانية متابعة التغطية الإعلانية',
            ],
            'realestate' => [
                'تمييز عروض العقارات الموثوقة',
                'جذب العملاء الجادين أولاً',
                'أولوية مراجعة الطلبات العقارية',
            ],
        ];

        $benefits = $baseBenefits[$accountType] ?? $baseBenefits['individual'];

        if (!empty($plan?->duration_days)) {
            $benefits[] = __('صلاحية التوثيق :days يوم', ['days' => $plan->duration_days]);
        }

        if (!empty($plan?->price) && $plan->price > 0) {
            $benefits[] = __('رسوم الاشتراك :price :currency', [
                'price' => number_format((float) $plan->price, 2),
                'currency' => $plan->currency ?? 'SAR',
            ]);
        } else {
            $benefits[] = __('توثيق مجاني للحسابات المؤهلة');
        }

        return array_values(array_unique(array_filter($benefits)));
    }

    protected function resolveAccountTypeSlug(?User $user, ?string $requested = null): string
    {
        $normalizedRequest = $this->normalizeAccountTypeSlug($requested);

        if ($normalizedRequest !== null) {
            return $normalizedRequest;
        }

        if ($user && $user->account_type) {
            return $this->mapAccountTypeIntToSlug((int) $user->account_type);
        }

        return 'individual';
    }

    protected function normalizeAccountTypeSlug($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            '1', 'individual', 'personal', 'customer', 'private' => 'individual',
            '2', 'realestate', 'real_estate', 'property' => 'realestate',
            '3', 'commercial', 'business', 'merchant', 'seller' => 'commercial',
            default => null,
        };
    }

    protected function mapAccountTypeIntToSlug(int $accountType): string
    {
        return match ($accountType) {
            User::ACCOUNT_TYPE_REAL_ESTATE => 'realestate',
            User::ACCOUNT_TYPE_SELLER => 'commercial',
            default => 'individual',
        };
    }

    protected function validateRequiredVerificationFields(string $accountType, Request $request): array
    {
        $requiredFields = VerificationField::query()
            ->where('account_type', $accountType)
            ->where('is_required', true)
            ->whereNull('deleted_at')
            ->pluck('name', 'id');

        $providedFields = $request->get('verification_field', []);
        $providedFiles = $request->file('verification_field_files', []);

        $missing = [];

        foreach ($requiredFields as $id => $name) {
            $hasValue = $this->hasVerificationInput($providedFields[$id] ?? null)
                || $this->hasVerificationInput($providedFiles[$id] ?? null);

            if (!$hasValue) {
                $missing[] = $name ?? ('field_' . $id);
            }
        }

        return $missing;
    }

    protected function hasVerificationInput($value): bool
    {
        if ($value instanceof UploadedFile) {
            return true;
        }

        if (is_array($value)) {
            return collect($value)->filter(fn($entry) => !empty($this->normalizeVerificationFieldValue($entry)))->isNotEmpty();
        }

        return !empty($this->normalizeVerificationFieldValue($value));
    }

    protected function normalizeVerificationFieldValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $normalized = trim($value);

            return $normalized === '' ? null : $normalized;
        }

        if (is_scalar($value)) {
            $normalized = trim((string) $value);

            return $normalized === '' ? null : $normalized;
        }

        if (is_iterable($value)) {
            $parts = [];

            foreach ($value as $part) {
                if ($part === null) {
                    continue;
                }

                if (!is_scalar($part)) {
                    continue;
                }

                $normalizedPart = trim((string) $part);

                if ($normalizedPart === '') {
                    continue;
                }

                $parts[] = $normalizedPart;
            }

            if (empty($parts)) {
                return null;
            }

            return implode(',', $parts);
        }

        return null;
    }



    



    


    /**
     * Get services based on category and is_main flag
     *
     * @param Request $request
     * @return void
     */


    




    


    


    



    


/**
 * أ¢â€‌ع©ط£آ¨أ¢â€¢ع¾ط·آµأ¢â€‌ع©ط£ع¾أ¢â€‌ع©ط¸â€کأ¢â€‌ع©ط¢â€‍ أ¢â€‌ع©ط£آ¢أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾ط·آ®أ¢â€‌ع©ط¢â€  Service أ¢â€¢ع¾ط·آ­أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط£آ« أ¢â€‌ع©ط£آ أ¢â€¢ع¾أ¢â€¢طŒأ¢â€‌ع©ط¢ظ¾أ¢â€‌ع©ط£ع¾أ¢â€‌ع©ط¢ظ¾أ¢â€¢ع¾ط·آ± JSON أ¢â€¢ع¾ط·آ´أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط£آ§أ¢â€¢ع¾أ¢â€“â€œأ¢â€¢ع¾ط·آ± أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ²أ¢â€¢ع¾أ¢â€¢â€“أ¢â€¢ع¾ط·آ°أ¢â€‌ع©ط£آ¨أ¢â€‌ع©ط£آ©.
 *  *
 * @param  Service  $s
 * @param  bool     $includeOwnerEmail أ¢â€‌ع©ط£آ§أ¢â€‌ع©ط¢â€‍ أ¢â€‌ع©ط£آ¨أ¢â€¢ع¾ط·آ´أ¢â€¢ع¾ط·آ° أ¢â€¢ع¾ط·آ²أ¢â€¢ع¾أ¢â€¢آ¢أ¢â€‌ع©ط£آ أ¢â€‌ع©ط£آ¨أ¢â€‌ع©ط¢â€  أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ°أ¢â€¢ع¾أ¢â€“â€™أ¢â€‌ع©ط£آ¨أ¢â€¢ع¾ط¢آ» أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ­أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط£آ¢أ¢â€¢ع¾ط·آ²أ¢â€¢ع¾أ¢â€“â€™أ¢â€‌ع©ط£ع¾أ¢â€‌ع©ط¢â€ أ¢â€‌ع©ط£آ¨ أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط£آ أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط£آ¢أ¢â€¢ع¾ط·آ§
 */
private function mapService(Service $s, bool $includeOwnerEmail = false): array
{



    $url = function (?string $path) {
        if (!$path) return null;
        if (preg_match('#^https?://#', $path)) return $path;
        return asset('storage/' . ltrim($path, '/'));
    };


    $expiry = $s->expiry_date;
    if ($expiry instanceof DateTimeInterface) {
        $expiry = $expiry->format('Y-m-d');
    } elseif ($expiry !== null) {
        $expiry = (string) $expiry;
    }

    $owner = $s->relationLoaded('owner') ? $s->getRelation('owner') : null;
    $ownerId = $owner
        ? (int) $owner->id
        : ($s->owner_id !== null ? (int) $s->owner_id : null);

    $avgRating = $s->avg_rating ?? $s->reviews_avg_rating ?? null;
    $avgRating = $avgRating !== null ? round((float) $avgRating, 2) : null;
    $reviewsCount = isset($s->reviews_count) ? (int) $s->reviews_count : null;

    return [
        'id'                => (int) $s->id,
        'category_id'       => (int) $s->category_id,
        'title'             => (string) $s->title,
        'description'       => (string) ($s->description ?? ''),
        'is_main'           => (bool) $s->is_main,
        'service_type'      => $s->service_type,
        'status'            => (bool) $s->status,
        'owner_id'          => $ownerId,
        'user_id'           => $ownerId,
        'views'             => (int) ($s->views ?? 0),
        'expiry_date'       => $expiry,

        // ط·آ¸ط¢آ£ط£آ  أ¢â€¢ع¾ط·آµأ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢ظ¾أ¢â€¢ع¾أ¢â€¢â€¢ أ¢â€¢ع¾أ¢â€¢آ£أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط£آ« أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط£آ أ¢â€‌ع©ط¢ظ¾أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾ط·آ²أ¢â€‌ع©ط£آ¨أ¢â€¢ع¾ط·آµ أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط£آ©أ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط£آ¨أ¢â€‌ع©ط£آ أ¢â€¢ع¾ط·آ± أ¢â€‌ع©ط£آ أ¢â€¢ع¾أ¢â€¢آ£ أ¢â€¢ع¾أ¢â€“â€™أ¢â€‌ع©ط£ع¾أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾ط·آ°أ¢â€¢ع¾أ¢â€¢â€“ أ¢â€‌ع©ط£آ¢أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط£آ أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ±
        'image'             => $url($s->image),
        'icon'              => $url($s->icon),

        // (أ¢â€¢ع¾ط·آ­أ¢â€¢ع¾أ¢â€¢آ¢أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢ظ¾أ¢â€‌ع©ط£آ¨ أ¢â€‌ع©ط£آ أ¢â€¢ع¾ط·آ²أ¢â€‌ع©ط£ع¾أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢ظ¾أ¢â€‌ع©ط£آ© أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط¢آ«أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط¢ظ¾)
        'image_url'         => $url($s->image),
        'icon_url'          => $url($s->icon),

        // أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آµأ¢â€‌ع©ط£آ©أ¢â€‌ع©ط£ع¾أ¢â€‌ع©ط¢â€‍ أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ´أ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط£آ¨أ¢â€¢ع¾ط¢آ»أ¢â€¢ع¾ط·آ±
        'is_paid'           => (bool) $s->is_paid,
        'price'             => $s->price !== null ? (float) $s->price : null,
        'currency'          => $s->currency,
        'price_note'        => $s->price_note,
        'has_custom_fields' => (bool) $s->has_custom_fields,
        'service_fields_schema' => $this->transformServiceFieldsSchema($s),


        'direct_to_user'    => (bool) $s->direct_to_user,
        'direct_user_id'    => $s->direct_user_id ? (int) $s->direct_user_id : null,
        'owner'             => $owner ? array_merge([
            'id'   => $ownerId,
            'name' => $owner->name,
        ], $includeOwnerEmail ? ['email' => $owner->email] : []) : null,


        'service_uid'       => $s->service_uid,

        // (أ¢â€¢ع¾ط·آ­أ¢â€¢ع¾أ¢â€¢آ¢أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢ظ¾أ¢â€‌ع©ط£آ¨) أ¢â€¢ع¾ط·آ²أ¢â€‌ع©ط£ع¾أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾أ¢â€“â€™أ¢â€‌ع©ط£آ¨أ¢â€¢ع¾ط¢آ« أ¢â€‌ع©ط£آ©أ¢â€¢ع¾ط¢آ» أ¢â€‌ع©ط£آ¨أ¢â€¢ع¾ط·آµأ¢â€¢ع¾ط·آ²أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾ط·آ´أ¢â€‌ع©ط£آ§أ¢â€¢ع¾ط·آ¯ أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ²أ¢â€¢ع¾أ¢â€¢â€“أ¢â€¢ع¾ط·آ°أ¢â€‌ع©ط£آ¨أ¢â€‌ع©ط£آ©
        'created_at'        => optional($s->created_at)->toISOString(),
        'updated_at'        => optional($s->updated_at)->toISOString(),
    ];

}




private function deleteServiceMedia(Service $service): void
{
    $disk = Storage::disk('public');

    foreach (['image', 'icon'] as $attribute) {
        $path = $service->{$attribute};

        if (empty($path) || preg_match('#^https?://#i', (string) $path)) {
            continue;
        }

        try {
            $disk->delete($path);
        } catch (Throwable) {
            // أ¢â€¢ع¾ط·آ²أ¢â€¢ع¾ط·آ´أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط£آ§أ¢â€‌ع©ط¢â€‍ أ¢â€¢ع¾ط·آ«أ¢â€‌ع©ط£آ¨ أ¢â€¢ع¾ط·آ«أ¢â€¢ع¾ط¢آ«أ¢â€¢ع¾أ¢â€¢â€“أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾ط·آ© أ¢â€‌ع©ط¢ظ¾أ¢â€‌ع©ط£آ¨ أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آµأ¢â€¢ع¾أ¢â€“â€کأ¢â€‌ع©ط¢ظ¾ أ¢â€‌ع©ط£آ أ¢â€‌ع©ط¢â€  أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ²أ¢â€¢ع¾ط¢آ«أ¢â€¢ع¾أ¢â€“â€œأ¢â€‌ع©ط£آ¨أ¢â€‌ع©ط¢â€  أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾أ¢â€¢آ£أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط£آ .
        }
    }
}


private function normalizeServiceFieldIconPath($path): ?string
{
    if ($path === null) {
        return null;
    }

    if (is_array($path)) {
        $path = Arr::first($path);
    }

    $path = trim((string) $path);

    if ($path === '' || strtolower($path) === 'null') {
        return null;
    }

    if (preg_match('#^https?://#i', $path)) {
        $parsed = parse_url($path, PHP_URL_PATH);
        if (is_string($parsed) && $parsed !== '') {
            $path = $parsed;
        }
    }

    $path = ltrim($path, '/');

    if (Str::startsWith($path, 'storage/')) {
        $path = substr($path, strlen('storage/'));
    }

    return $path !== '' ? $path : null;
}

private function buildPublicStorageUrl(?string $path): ?string
{
    if ($path === null || $path === '') {
        return null;
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $normalized = ltrim($path, '/');
    if ($normalized === '') {
        return null;
    }

    return Storage::disk('public')->url($normalized);
}


private function transformServiceFieldsSchema(Service $service): array
{

    $service->loadMissing(['serviceCustomFields.value', 'serviceCustomFieldValues']);


    $fields = $service->relationLoaded('serviceCustomFields')
        ? $service->getRelation('serviceCustomFields')->sortBy(function (ServiceCustomField $field) {
            $sequence = is_numeric($field->sequence) ? (int) $field->sequence : 0;
            return sprintf('%010d-%010d', $sequence, $field->id ?? 0);
        })->values()
        : $service->serviceCustomFields()->orderBy('sequence')->orderBy('id')->get();

    if ($fields->isNotEmpty()) {
        $valueIndex = $service->relationLoaded('serviceCustomFieldValues')
            ? $service->getRelation('serviceCustomFieldValues')->keyBy('service_custom_field_id')
            : $service->serviceCustomFieldValues()->get()->keyBy('service_custom_field_id');

        return $fields->map(function (ServiceCustomField $field) use ($valueIndex) {


            $payload = $field->toSchemaPayload();


            $fieldKey = is_string($payload['name'] ?? null)
                ? trim((string) $payload['name'])
                : '';
            $fieldLabel = is_string($payload['title'] ?? null)
                ? trim((string) $payload['title'])
                : '';

            if ($fieldLabel === '' && isset($payload['label'])) {
                $fieldLabel = trim((string) $payload['label']);
            }

            if ($fieldLabel === '' && $fieldKey !== '') {
                $fieldLabel = Str::headline(str_replace('_', ' ', $fieldKey));
            }

            if ($fieldLabel === '') {
                $fieldLabel = Str::headline('field_' . $field->id);
            }

            $fieldName = $fieldKey !== '' ? $fieldKey : 'field_' . $field->id;


            $properties = [];
            foreach (['min', 'max', 'min_length', 'max_length'] as $prop) {
                if (array_key_exists($prop, $payload) && $payload[$prop] !== null && $payload[$prop] !== '') {
                    $properties[$prop] = $payload[$prop];
                }
            }


            $status = array_key_exists('status', $payload)
                ? (bool) $payload['status']
                : (array_key_exists('active', $payload) ? (bool) $payload['active'] : true);

            $valueModel = $field->relationLoaded('value')
                ? $field->getRelation('value')
                : $valueIndex->get($field->id);

            $valuePayload = $this->formatServiceFieldValueForApi($field, $valueModel);
            $imagePath = $this->normalizeServiceFieldIconPath($payload['image'] ?? null);
            $imageUrl  = $this->buildPublicStorageUrl($imagePath);



            $noteValue = $payload['note'] ?? '';
            if (!is_string($noteValue)) {
                $noteValue = (string) $noteValue;
            }


            $fieldData = array_merge([

                'id'         => $field->id,
                'name'       => $fieldName,
                'key'        => $fieldKey !== '' ? $fieldKey : null,
                'form_key'   => $fieldKey !== '' ? $fieldKey : null,
                'title'      => $fieldLabel,
                'label'      => $fieldLabel,
                'type'       => $payload['type'],
                'required'   => (bool) ($payload['required'] ?? false),
                'note'       => $noteValue,
                'sequence'   => (int) ($payload['sequence'] ?? 0),
                'values'     => $payload['values'] ?? [],
                'properties' => $properties,
                'image'      => $imageUrl,
                'image_path' => $imagePath,
                
                'meta'       => $payload['meta'] ?? null,
                'status'     => $status,
                'active'     => $status,
            ], $valuePayload);


            $label = $payload['title'] ?? $payload['label'] ?? $payload['name'];
            if (!is_string($label) || $label === '') {
                $label = $fieldData['name'];
            }

            $fieldData['label'] = $label;
            $fieldData['display_name'] = $label;
            $fieldData['form_key'] = $fieldData['name'];
            $fieldData['note_text'] = $fieldData['note'];

            if ($fieldData['image'] === null) {
                unset($fieldData['image']);
            }
            if (array_key_exists('image_path', $fieldData) && ($fieldData['image_path'] === null || $fieldData['image_path'] === '')) {
                unset($fieldData['image_path']);
            }


            if (array_key_exists('key', $fieldData) && $fieldData['key'] === null) {
                unset($fieldData['key']);
            }
            if (array_key_exists('form_key', $fieldData) && $fieldData['form_key'] === null) {
                unset($fieldData['form_key']);
            }
            

            if ($fieldData['meta'] === null) {
                unset($fieldData['meta']);
            }
            if (empty($fieldData['properties'])) {
                unset($fieldData['properties']);
            }
            if (!is_array($fieldData['values'])) {
                $fieldData['values'] = [];
            }
            if (array_key_exists('file_urls', $fieldData) && empty($fieldData['file_urls'])) {
                unset($fieldData['file_urls']);
            }
            if (array_key_exists('file_url', $fieldData) && empty($fieldData['file_url'])) {
                unset($fieldData['file_url']);
            }
            if (array_key_exists('display_value', $fieldData) && ($fieldData['display_value'] === null || $fieldData['display_value'] === '')) {
                unset($fieldData['display_value']);
            }
            if (array_key_exists('value_raw', $fieldData) && ($fieldData['value_raw'] === null || $fieldData['value_raw'] === '')) {
                unset($fieldData['value_raw']);
            }
            if (array_key_exists('value_updated_at', $fieldData) && $fieldData['value_updated_at'] === null) {
                unset($fieldData['value_updated_at']);
            }
            if (array_key_exists('value_id', $fieldData) && $fieldData['value_id'] === null) {
                unset($fieldData['value_id']);
            }

            return $fieldData;
        })->values()->all();
    }


    $schema = $service->service_fields_schema ?? [];

    if (!is_array($schema) || $schema === []) {
        return [];
    }


    $service->loadMissing(['serviceCustomFields']);

    $serviceFieldModels = $service->serviceCustomFields ?? collect();
    $serviceFieldModelsById = $serviceFieldModels->keyBy('id');
    $serviceFieldModelsByKey = $serviceFieldModels->mapWithKeys(static function ($field) {
        /** @var \App\Models\ServiceCustomField $field */
        $key = $field->form_key;
        return $key !== '' ? [$key => $field] : [];
    });



    $normalized = [];
    $fallbackIndex = 1;

    foreach ($schema as $field) {
        if (!is_array($field)) {
            continue;
        }

        $sequence = (int) ($field['sequence'] ?? $fallbackIndex);
        $title    = trim((string) ($field['title'] ?? $field['label'] ?? ''));
        $name     = trim((string) ($field['name'] ?? $field['key'] ?? ''));
        if ($name === '') {
            $name = $title !== '' ? str_replace(' ', '_', strtolower($title)) : 'field_' . $fallbackIndex;
        }




        $serviceFieldModel = null;
        if (isset($field['id'])) {
            $serviceFieldModel = $serviceFieldModelsById->get((int) $field['id']);
        }

        if (!$serviceFieldModel && $name !== '' && $serviceFieldModelsByKey->has($name)) {
            $serviceFieldModel = $serviceFieldModelsByKey->get($name);
        }

        if ($title === '' && $serviceFieldModel) {
            $modelName = trim((string) ($serviceFieldModel->name ?? ''));
            if ($modelName !== '') {
                $title = $modelName;
            }
        }

        if ($title === '' && isset($field['meta']['label'])) {
            $metaLabel = trim((string) $field['meta']['label']);
            if ($metaLabel !== '') {
                $title = $metaLabel;
            }
        }



        $values = $field['values'] ?? [];
        if (!is_array($values)) {
            $values = [];
        }
        $values = array_values(array_map(static function ($value) {
            if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
                return (string) $value;
            }

            return $value;
        }, $values));


        $noteValue = $field['note'] ?? '';
        if (!is_string($noteValue)) {
            $noteValue = (string) $noteValue;
        }



        $properties = [];
        foreach (['min', 'max', 'min_length', 'max_length'] as $prop) {
            if (array_key_exists($prop, $field) && $field[$prop] !== null && $field[$prop] !== '') {
                $properties[$prop] = $field[$prop];
            }
        }


        $status = array_key_exists('status', $field)
            ? (bool) $field['status']
            : (array_key_exists('active', $field) ? (bool) $field['active'] : true);
        $type = (string) ($field['type'] ?? 'textbox');

        $imagePath = $this->normalizeServiceFieldIconPath($field['image'] ?? $field['image_path'] ?? null);
        $imageUrl = $this->buildPublicStorageUrl($imagePath);

        $entry = [
            
            'name'       => $name,
            'title'      => $title,
            'type'       => $type,
            'required'   => (bool) ($field['required'] ?? false),
            'note'       => $noteValue,
            'sequence'   => $sequence,
            'values'     => $values,
            'properties' => $properties,
            'image'      => $imageUrl,
            'image_path' => $imagePath,
            'status'     => $status,
            'active'     => $status,
            'value'      => $type === 'checkbox' ? [] : ($type === 'fileinput' ? [] : null),

        ];


        $label = $title !== '' ? $title : $name;
        $entry['title'] = $label;
        $entry['label'] = $label;
        $entry['display_name'] = $label;
        $entry['form_key'] = $name;
        $entry['note_text'] = $entry['note'];

        if ($entry['image'] === null) {
            unset($entry['image']);
        }
        if ($entry['image_path'] === null) {
            unset($entry['image_path']);
        }

        $normalized[] = $entry;

        $fallbackIndex++;
    }

    usort($normalized, static fn(array $a, array $b) => ($a['sequence'] ?? 0) <=> ($b['sequence'] ?? 0));

    return $normalized;
}


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

    /**
     * Get currency rates
     *
     * @param Request $request
     * @return void
     */
    

    
    /**
     * Add or update currency rate
     *
     * @param Request $request
     * @return void
     */
    


    /**
     * Get specific categories if no category_id is provided
     *
     * @param Request $request
     * @return void
     */
    // 

    
    /**
     * Get all active challenges
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */



    

    
    /**
     * Get user's referral points
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    

    
    /**
     * Get user orders
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    

    
    /**
     * Get delivery prices
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    





    








  



    


    /**
     * أ¢â€¢ع¾ط·آ­أ¢â€‌ع©ط£آ¢أ¢â€‌ع©ط£آ أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍ أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ²أ¢â€¢ع¾أ¢â€‌â€ڑأ¢â€¢ع¾ط·آ´أ¢â€‌ع©ط£آ¨أ¢â€‌ع©ط¢â€‍ أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط£آ أ¢â€¢ع¾أ¢â€‌â€ڑأ¢â€¢ع¾ط·آ²أ¢â€¢ع¾ط¢آ«أ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط£آ أ¢â€‌ع©ط£آ¨أ¢â€‌ع©ط¢â€ 
     */
    


    /**
     * أ¢â€¢ع¾ط·آ²أ¢â€¢ع¾ط·آµأ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط£آ¨أ¢â€¢ع¾ط·آ³ أ¢â€‌ع©ط£آ¢أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط£آ أ¢â€¢ع¾ط·آ± أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط£آ أ¢â€¢ع¾أ¢â€“â€™أ¢â€‌ع©ط£ع¾أ¢â€¢ع¾أ¢â€“â€™ أ¢â€¢ع¾ط·آ°أ¢â€¢ع¾أ¢â€¢آ£أ¢â€¢ع¾ط¢آ» أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ²أ¢â€¢ع¾ط·آµأ¢â€‌ع©ط£آ©أ¢â€‌ع©ط£آ© أ¢â€‌ع©ط£آ أ¢â€‌ع©ط¢â€  OTP
     */
    



    /**
     * @return array<string, mixed>
     */
    private function buildReferralLocationPayload(Request $request): array
    {
        $lat = $request->has('lat') ? $request->input('lat') : null;
        $lng = $request->has('lng') ? $request->input('lng') : null;

        return [
            'lat' => is_numeric($lat) ? (float) $lat : null,
            'lng' => is_numeric($lng) ? (float) $lng : null,
            'device_time' => $request->input('device_time'),
            'admin_area' => $request->input('admin_area'),
        ];
    }

    /**
     * Resolve a non-null display name for seller accounts.
     */
    private function fallbackSellerName(Request $request, array $userData = [], ?User $user = null): string
    {
        $candidate = $request->input('business_name')
            ?? ($userData['business_name'] ?? null)
            ?? ($userData['name'] ?? null)
            ?? ($user?->name ?? null);

        $candidate = is_string($candidate) ? trim($candidate) : '';
        if ($candidate !== '') {
            return $candidate;
        }

        $mobile = $request->mobile ?? ($userData['mobile'] ?? ($user?->mobile ?? ''));
        $normalizedMobile = preg_replace('/\D+/', '', (string) $mobile);
        if (!empty($normalizedMobile)) {
            return 'store_' . $normalizedMobile;
        }

        return 'store_' . Str::uuid()->toString();
    }



    private function buildReferralRequestMeta(Request $request): array
    {
        $meta = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        $requestId = $request->headers->get('X-Request-Id');

        if (!empty($requestId)) {
            $meta['request_id'] = $requestId;
        }

        return array_filter($meta, static fn ($value) => $value !== null && $value !== '');
    }


    /**
     * أ¢â€‌ع©ط£آ أ¢â€¢ع¾أ¢â€¢آ£أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ´أ¢â€¢ع¾ط·آ± أ¢â€‌ع©ط£آ¢أ¢â€‌ع©ط£ع¾أ¢â€¢ع¾ط¢آ» أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ­أ¢â€¢ع¾ط·آµأ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ±
     * 
     * @param string $code أ¢â€‌ع©ط£آ¢أ¢â€‌ع©ط£ع¾أ¢â€¢ع¾ط¢آ» أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ­أ¢â€¢ع¾ط·آµأ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ±
     * @param User $user أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط£آ أ¢â€¢ع¾أ¢â€‌â€ڑأ¢â€¢ع¾ط·آ²أ¢â€¢ع¾ط¢آ«أ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط£آ  أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ´أ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط£آ¨أ¢â€¢ع¾ط¢آ»
     * @param string $contactInfo أ¢â€‌ع©ط£آ أ¢â€¢ع¾أ¢â€¢آ£أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط£ع¾أ¢â€‌ع©ط£آ أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾ط·آ² أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾ط·آ²أ¢â€¢ع¾أ¢â€¢طŒأ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍
     * @param array<string, mixed> $locationPayload
     * @return array<string, mixed>
     * 
     * 
     */
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
            // أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ°أ¢â€¢ع¾ط·آµأ¢â€¢ع¾ط·آ³ أ¢â€¢ع¾أ¢â€¢آ£أ¢â€‌ع©ط¢â€  أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط£آ أ¢â€¢ع¾أ¢â€‌â€ڑأ¢â€¢ع¾ط·آ²أ¢â€¢ع¾ط¢آ«أ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط£آ  أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾أ¢â€“â€کأ¢â€‌ع©ط£آ¨ أ¢â€‌ع©ط£آ¨أ¢â€‌ع©ط£آ أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط£آ¢ أ¢â€‌ع©ط£آ¢أ¢â€‌ع©ط£ع¾أ¢â€¢ع¾ط¢آ» أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ­أ¢â€¢ع¾ط·آµأ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ±
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
            
            // أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آµأ¢â€¢ع¾أ¢â€¢طŒأ¢â€‌ع©ط£ع¾أ¢â€‌ع©ط¢â€‍ أ¢â€¢ع¾أ¢â€¢آ£أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط£آ« أ¢â€¢ع¾ط·آ«أ¢â€‌ع©ط£ع¾أ¢â€‌ع©ط¢â€‍ أ¢â€¢ع¾ط·آ²أ¢â€¢ع¾ط·آµأ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط£آ¨ أ¢â€‌ع©ط¢â€ أ¢â€¢ع¾أ¢â€‌آ¤أ¢â€¢ع¾أ¢â€¢â€“


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
            
            // أ¢â€¢ع¾ط·آ­أ¢â€‌ع©ط¢â€ أ¢â€¢ع¾أ¢â€‌آ¤أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾ط·آ© أ¢â€¢ع¾أ¢â€‌â€ڑأ¢â€¢ع¾ط·آ´أ¢â€‌ع©ط¢â€‍ أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ­أ¢â€¢ع¾ط·آµأ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ± أ¢â€‌ع©ط£آ أ¢â€¢ع¾أ¢â€¢آ£ challenge_id أ¢â€‌ع©ط£ع¾ points
                 $referral = Referral::create([
                'referrer_id' => $referrer->id,
                'referred_user_id' => $user->id,
                'challenge_id' => $challenge->id,
                'points' => $challenge->points_per_referral,
            ]);
            
            // أ¢â€¢ع¾ط·آ²أ¢â€‌ع©ط£آ©أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط£آ¨أ¢â€‌ع©ط¢â€‍ أ¢â€¢ع¾أ¢â€¢آ£أ¢â€¢ع¾ط¢آ»أ¢â€¢ع¾ط¢آ» أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ­أ¢â€¢ع¾ط·آµأ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾ط·آ² أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط£آ أ¢â€¢ع¾أ¢â€¢â€“أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط£ع¾أ¢â€¢ع¾ط·آ°أ¢â€¢ع¾ط·آ± أ¢â€¢ع¾ط·آ°أ¢â€‌ع©ط£آ أ¢â€‌ع©ط£آ©أ¢â€¢ع¾ط¢آ»أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾أ¢â€“â€™ أ¢â€‌ع©ط£ع¾أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾ط·آµأ¢â€¢ع¾ط¢آ»
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
    


    private function sendReferralStatusNotification(?User $referrer, ?User $referredUser, string $messageTranslationKey): array
    {
        $recipientIds = collect([$referrer?->id, $referredUser?->id])
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($recipientIds)) {
            return [
                'attempted' => false,
                'result' => 'failure',
                'reason' => 'missing_recipients',
            ];
        }

        $tokens = UserFcmToken::whereIn('user_id', $recipientIds)
            ->pluck('fcm_token')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($tokens)) {
            return [
                'attempted' => false,
                'result' => 'failure',
                'recipients' => $recipientIds,
                'reason' => 'missing_tokens',
            ];
        }

        $title = __('notifications.referral.status_title');
        $body = __($messageTranslationKey);

        $response = NotificationService::sendFcmNotification($tokens, $title, $body, 'referral_status');

        $meta = [
            'attempted' => true,
            'recipients' => $recipientIds,
            'tokens' => count($tokens),
            'message_key' => $messageTranslationKey,
            'result' => 'success',
        ];

        if (is_array($response)) {
            $responseSummary = array_filter([
                'error' => $response['error'] ?? null,
                'message' => $response['message'] ?? null,
                'code' => $response['code'] ?? null,
            ], static fn ($value) => $value !== null && $value !== '');

            if (!empty($responseSummary)) {
                $meta['response'] = $responseSummary;
            }

            if (($response['error'] ?? false) === true) {
                $meta['result'] = 'failure';
            }
        } elseif ($response === false) {
            $meta['result'] = 'failure';
        }

        return $meta;
    }
    

    /**
     * Get user profile statistics
     */
    


    /**
     * أ¢â€¢ع¾ط·آµأ¢â€‌ع©ط¢ظ¾أ¢â€¢ع¾أ¢â€¢â€¢ أ¢â€‌ع©ط£آ أ¢â€‌ع©ط£ع¾أ¢â€‌ع©ط£آ©أ¢â€¢ع¾أ¢â€¢آ£ أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط£آ أ¢â€¢ع¾أ¢â€‌â€ڑأ¢â€¢ع¾ط·آ²أ¢â€¢ع¾ط¢آ«أ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط£آ  أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آµأ¢â€¢ع¾أ¢â€‌â€ڑأ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾ط·آ°أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾ط·آ² أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط¢ظ¾أ¢â€¢ع¾أ¢â€“â€™أ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط£آ¨أ¢â€¢ع¾ط·آ±
     */
    





    private function resolveManualPayableType(?string $type): ?string {
        if (empty($type)) {
            return null;
        }

        $type = trim($type);

        if (class_exists($type) && is_subclass_of($type, EloquentModel::class)) {
            return $type;
        }

        $normalized = strtolower($type);

        if (ManualPaymentRequest::isOrderPayableType($type) || ManualPaymentRequest::isOrderPayableType($normalized)) {

            return Order::class;
        }

        $packageAliases = [
            'package',
            'packages',
            'app\\package',
            'app\\models\\package',
        ];

        if (in_array($normalized, $packageAliases, true)) {
            return Package::class;
        }

        $itemAliases = [
            'item',
            'items',
            'ad',
            'ads',
            'advertisement',
            'advertisements',
            'listing',
            'listings',
            'app\\item',
            'app\\models\\item',
        ];

        if (in_array($normalized, $itemAliases, true)) {
            return Item::class;
        }

        $serviceAliases = [
            'service',
            'services',
            'app\\models\\service',
        ];

        if (in_array($normalized, $serviceAliases, true)) {
            return Service::class;
        }

        $walletAliases = [
            



            
            ManualPaymentRequest::PAYABLE_TYPE_WALLET_TOP_UP,
            'wallet-top-up',
            'wallet_top_up',
            'wallet',
            'wallettopup',
        ];

        if (in_array($normalized, $walletAliases, true)) {
            return ManualPaymentRequest::PAYABLE_TYPE_WALLET_TOP_UP;
        }


        return null;
    }

    private function getDefaultCurrencyCode(): ?string {
        $settingKeys = ['currency_code', 'currency', 'default_currency', 'currency_symbol'];

        foreach ($settingKeys as $key) {
            $value = Setting::where('name', $key)->value('value');

            if (!empty($value)) {
                return strtoupper($value);
            }
        }

        $fallback = $this->normalizeCurrencyCode(config('app.currency'));

        return $fallback ?? $this->getWalletCurrencyCode();
    }

    private function generateManualPaymentSignedUrl(?string $path): ?string {
        if (empty($path)) {
            return null;
        }

        $disk = Storage::disk('public');

        try {
            if (method_exists($disk, 'temporaryUrl')) {
                return $disk->temporaryUrl($path, now()->addMinutes(10));
            }
        } catch (Throwable) {
            // Driver may not support temporary URLs; fall back to standard URL below.
        }

        return url($disk->url($path));
    }







    


    


    


    



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

    private function appendManualPaymentReceiptMeta(array $meta, ?string $receiptPath): array
    {
        if (! is_string($receiptPath) || trim($receiptPath) === '') {
            return $this->cleanupManualPaymentMeta($meta);
        }

        $normalizedPath = trim($receiptPath);

        $attachment = $this->sanitizeManualPaymentAttachment([
            'name' => 'receipt',
            'path' => $normalizedPath,
            'disk' => 'public',
        ]);

        if ($attachment !== []) {
            $attachments = $this->mergeManualPaymentAttachmentCollections(
                is_array(data_get($meta, 'attachments')) ? data_get($meta, 'attachments') : [],
                [$attachment]
            );
            if ($attachments !== []) {
                data_set($meta, 'attachments', $attachments);
            }

            $manualAttachments = $this->mergeManualPaymentAttachmentCollections(
                is_array(data_get($meta, 'manual.attachments')) ? data_get($meta, 'manual.attachments') : [],
                [$attachment]
            );
            if ($manualAttachments !== []) {
                data_set($meta, 'manual.attachments', $manualAttachments);
            }

            data_set($meta, 'receipt', array_filter([
                'path' => $normalizedPath,
                'disk' => 'public',
            ], static fn ($value) => $value !== null && $value !== ''));

            data_set($meta, 'manual.receipt', array_filter([
                'path' => $normalizedPath,
                'disk' => 'public',
            ], static fn ($value) => $value !== null && $value !== ''));
        }

        return $this->cleanupManualPaymentMeta($meta);
    }

    private function mergeManualPaymentMeta(array $existingMeta, array $updates): array
    {
        if ($updates === []) {
            return $this->cleanupManualPaymentMeta($existingMeta);
        }

        $merged = array_replace_recursive($existingMeta, $updates);

        $mergedAttachments = $this->mergeManualPaymentAttachmentCollections(
            is_array(data_get($existingMeta, 'attachments')) ? data_get($existingMeta, 'attachments') : [],
            is_array(data_get($updates, 'attachments')) ? data_get($updates, 'attachments') : []
        );

        if ($mergedAttachments !== []) {
            data_set($merged, 'attachments', $mergedAttachments);
        } else {
            Arr::forget($merged, 'attachments');
        }

        $mergedManualAttachments = $this->mergeManualPaymentAttachmentCollections(
            is_array(data_get($existingMeta, 'manual.attachments')) ? data_get($existingMeta, 'manual.attachments') : [],
            is_array(data_get($updates, 'manual.attachments')) ? data_get($updates, 'manual.attachments') : []
        );

        if ($mergedManualAttachments !== []) {
            data_set($merged, 'manual.attachments', $mergedManualAttachments);
        } else {
            Arr::forget($merged, 'manual.attachments');
        }

        return $this->cleanupManualPaymentMeta($merged);
    }

    private function mergeManualPaymentAttachmentCollections(array $existing, array $additional): array
    {
        $collection = [];

        $push = static function (array $source) use (&$collection): void {
            foreach ($source as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $sanitized = array_filter($item, static function ($value) {
                    if ($value === null) {
                        return false;
                    }

                    if (is_string($value)) {
                        return trim($value) !== '';
                    }

                    if (is_array($value)) {
                        return $value !== [];
                    }

                    return true;
                });

                if ($sanitized === []) {
                    continue;
                }

                $path = array_key_exists('path', $sanitized)
                    ? trim((string) $sanitized['path'])
                    : '';
                $url = array_key_exists('url', $sanitized)
                    ? trim((string) $sanitized['url'])
                    : '';

                if ($path === '' && $url === '') {
                    continue;
                }

                $disk = array_key_exists('disk', $sanitized)
                    ? trim((string) $sanitized['disk'])
                    : '';

                $key = implode('|', array_filter([$disk, $path, $url]));

                $sanitized['path'] = $path;
                if ($disk !== '') {
                    $sanitized['disk'] = $disk;
                } else {
                    unset($sanitized['disk']);
                }

                if ($url !== '') {
                    $sanitized['url'] = $url;
                } else {
                    unset($sanitized['url']);
                }

                if ($path === '') {
                    unset($sanitized['path']);
                }

                if (! array_key_exists($key, $collection)) {
                    $collection[$key] = $sanitized;
                    continue;
                }

                $collection[$key] = array_replace($collection[$key], $sanitized);
            }
        };

        $push($existing);
        $push($additional);

        return array_values(array_filter($collection, static fn ($item) => is_array($item) && $item !== []));
    }

    private function sanitizeManualPaymentAttachment(array $attachment): array
    {
        return array_filter($attachment, static function ($value) {
            if ($value === null) {
                return false;
            }

            if (is_string($value)) {
                return trim($value) !== '';
            }

            if (is_array($value)) {
                return $value !== [];
            }

            return true;
        });
    }

    private function extractManualPaymentMetadataPayload(Request $request): ?array
    {
        $metadata = $request->input('metadata');

        if ($metadata instanceof Collection) {
            $metadata = $metadata->toArray();
        }

        if (is_string($metadata) && $metadata !== '') {
            try {
                $decoded = json_decode($metadata, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $metadata = $decoded;
                }
            } catch (Throwable) {
                $metadata = [];
            }
        }

        if (! is_array($metadata)) {
            $metadata = [];
        }

        $metaInput = $request->input('meta');
        if ($metaInput instanceof Collection) {
            $metaInput = $metaInput->toArray();
        }

        if (is_array($metaInput)) {
            $metaMetadata = data_get($metaInput, 'metadata');
            if (is_array($metaMetadata)) {
                $metadata = array_replace_recursive($metaMetadata, $metadata);
            }
        }

        $normalized = $this->sanitizeManualPaymentMetadataArray($metadata);

        return $normalized === [] ? null : $normalized;
    }

    private function sanitizeManualPaymentMetadataArray($value): array
    {
        if ($value instanceof Collection) {
            $value = $value->toArray();
        }

        if (is_string($value)) {
            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $value = $decoded;
                }
            } catch (Throwable) {
                return [];
            }
        }

        if (! is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $key => $item) {
            $normalizedKey = is_string($key) ? trim($key) : $key;

            if ($normalizedKey === '' || $normalizedKey === null) {
                continue;
            }

            if ($item instanceof Collection) {
                $item = $item->toArray();
            }

            if (is_array($item)) {
                $nested = $this->sanitizeManualPaymentMetadataArray($item);
                if ($nested !== []) {
                    $result[$normalizedKey] = $nested;
                }
                continue;
            }

            if ($item instanceof DateTimeInterface) {
                $result[$normalizedKey] = Carbon::createFromInterface($item)->toIso8601String();
                continue;
            }

            if (is_string($item)) {
                $trimmed = trim($item);
                if ($trimmed !== '') {
                    $result[$normalizedKey] = $trimmed;
                }
                continue;
            }

            if (is_numeric($item) || is_bool($item)) {
                $result[$normalizedKey] = $item;
                continue;
            }

            if ($item === null) {
                continue;
            }

            $stringified = trim((string) $item);
            if ($stringified !== '') {
                $result[$normalizedKey] = $stringified;
            }
        }

        return $result;
    }

    private function cleanupManualPaymentMeta(array $meta): array
    {
        foreach ($meta as $key => $value) {
            if (is_array($value)) {
                $normalized = $this->cleanupManualPaymentMeta($value);
                if ($normalized === []) {
                    unset($meta[$key]);
                } else {
                    $meta[$key] = $normalized;
                }
                continue;
            }

            if ($value === null) {
                unset($meta[$key]);
                continue;
            }

            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed === '') {
                    unset($meta[$key]);
                } else {
                    $meta[$key] = $trimmed;
                }
            }
        }

        return $meta;
    }

    private function normalizeManualPaymentString($value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeManualPaymentDateValue($value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::createFromInterface($value)->toIso8601String();
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        try {
            return Carbon::parse($trimmed)->toIso8601String();
        } catch (Throwable) {
            return $trimmed;
        }
    }


    private function normalizeManualPaymentRequestFilters(array $input): array
    {
        $filters = [
            'status' => $this->normalizeManualPaymentRequestStatus($input['status'] ?? null),
            'payment_gateway' => $this->normalizeManualPaymentGateway(
                $input['payment_gateway'] ?? ($input['gateway'] ?? null)
            ),
            'department' => null,
            'page' => $this->extractIntegerFromKeys($input, ['page', 'current_page']),
            'per_page' => $this->extractIntegerFromKeys($input, ['per_page', 'limit', 'page_size']),
        ];

        if (array_key_exists('department', $input)) {
            $rawDepartment = is_string($input['department']) ? trim($input['department']) : $input['department'];
            if (is_string($rawDepartment) && $rawDepartment !== '' && strtolower($rawDepartment) !== 'null') {
                $filters['department'] = $rawDepartment;
            }
        }

        return $filters;
    }

    private function normalizeManualPaymentRequestStatus($value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        if ($normalized === '' || $normalized === 'null') {
            return null;
        }

        $map = [
            'pending' => ManualPaymentRequest::STATUS_PENDING,
            'in_review' => ManualPaymentRequest::STATUS_UNDER_REVIEW,
            'in-review' => ManualPaymentRequest::STATUS_UNDER_REVIEW,
            'review' => ManualPaymentRequest::STATUS_UNDER_REVIEW,
            'reviewing' => ManualPaymentRequest::STATUS_UNDER_REVIEW,
            'under_review' => ManualPaymentRequest::STATUS_UNDER_REVIEW,
            'under-review' => ManualPaymentRequest::STATUS_UNDER_REVIEW,
            'approved' => ManualPaymentRequest::STATUS_APPROVED,
            'accepted' => ManualPaymentRequest::STATUS_APPROVED,
            'completed' => ManualPaymentRequest::STATUS_APPROVED,
            'rejected' => ManualPaymentRequest::STATUS_REJECTED,
            'declined' => ManualPaymentRequest::STATUS_REJECTED,
        ];

        return $map[$normalized] ?? ($normalized === ManualPaymentRequest::STATUS_PENDING
            || $normalized === ManualPaymentRequest::STATUS_UNDER_REVIEW
            || $normalized === ManualPaymentRequest::STATUS_APPROVED
            || $normalized === ManualPaymentRequest::STATUS_REJECTED
            ? $normalized
            : null);
    }

    private function normalizeManualPaymentGateway($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        if ($normalized === '') {
            return null;
        }

        foreach ($this->manualPaymentGatewayAliasMap() as $canonical => $aliases) {
            if (in_array($normalized, $aliases, true)) {
                return $canonical;
            }
        }

        return $normalized;
    }

    private function expandManualPaymentGatewayAliases(string $gateway): array
    {
        $canonical = $this->normalizeManualPaymentGateway($gateway);

        if ($canonical === null) {
            return [];
        }

        $aliases = $this->manualPaymentGatewayAliasMap()[$canonical] ?? [$canonical];

        return array_values(array_unique($aliases));
    }

    private function manualPaymentGatewayAliasMap(): array
    {
        return [
            'manual_banks' => [
                'manual_banks',
                'manual_bank',
                'manual',
                'manual-bank',
                'manual-banks',
                'manual bank',
                'manual banks',
                'manual_payment',
                'manual-payment',
                'manual payment',
                'manualpayment',
                'manualpayments',
                'manualbank',
                'manualbanking',
                'manualbanks',
                'manual_transfer',
                'manual-transfer',
                'manual transfers',
                'manual-transfers',
                'manualtransfers',
                'manualtransfer',
                'offline',
                'internal',
                'bank',
                'bank_transfer',
                'bank-transfer',
                'bank transfer',
                'banktransfer',

            ],
            'east_yemen_bank' => [
                'east_yemen_bank',
                'east-yemen-bank',
                'east yemen bank',
                'eastyemenbank',
                'east',
                'east_yemen',
                'east-yemen',
                'east yemen',
                'bankalsharq',
                'bank_alsharq',
                'bank-alsharq',
                'bank alsharq',
                'bankalsharqbank',
                'bank_alsharq_bank',
                'bank-alsharq-bank',
                'bank alsharq bank',
                'alsharq',
                'al-sharq',
                'al sharq',
            ],
            'wallet' => [
                'wallet',
                'wallet_balance',
                'wallet-balance',
                'wallet balance',
                'wallet_gateway',
                'wallet-gateway',
                'wallet gateway',
                'wallet_top_up',
                'wallet-top-up',
                'wallet top up',
                'wallet-topup',
                'wallet topup',
                'walletpayment',
                'wallet_payment',
                'wallet-payment',
                'wallet payment',
                'wallettopup',
            ],
            'cash' => [
                'cash',
                'cod',
                'cash_on_delivery',
                'cash-on-delivery',
                'cash on delivery',
                'cashcollection',
                'cash_collection',
                'cash-collection',
                'cash collection',
                'cashcollect',
                'cash_collect',
                'cash-collect',
            ],
        ];
    }





    private function applyManualPaymentRequestVisibilityScope(Builder $query, int $userId): Builder
    {
        $orderPayableTypes = ManualPaymentRequest::orderPayableTypeTokens();

        return $query->where(static function (Builder $builder) use ($userId, $orderPayableTypes) {
            
            $builder->where('manual_payment_requests.user_id', $userId)
                ->orWhere(static function (Builder $ordersScope) use ($userId, $orderPayableTypes) {
                    $ordersScope
                        ->whereIn(DB::raw('LOWER(manual_payment_requests.payable_type)'), $orderPayableTypes)

                        ->whereExists(static function ($subQuery) use ($userId) {
                            $subQuery
                                ->select(DB::raw('1'))
                                ->from('orders')
                                ->whereColumn('orders.id', 'manual_payment_requests.payable_id')
                                ->where(static function ($orderVisibility) use ($userId) {
                                    $orderVisibility
                                        ->where('orders.user_id', $userId)
                                        ->orWhere('orders.seller_id', $userId);
                                });
                        });
                })
                ->orWhereExists(static function ($subQuery) use ($userId, $orderPayableTypes) {
                    $subQuery
                        ->select(DB::raw('1'))
                        ->from('payment_transactions')
                        ->join('orders', static function ($join) use ($orderPayableTypes) {

                            $join
                                ->on('orders.id', '=', 'payment_transactions.payable_id')
                                ->whereIn(
                                    DB::raw('LOWER(payment_transactions.payable_type)'),
                                    $orderPayableTypes
                                );

                                
                        })
                        ->whereColumn('payment_transactions.manual_payment_request_id', 'manual_payment_requests.id')
                        ->where(static function ($orderVisibility) use ($userId) {
                            $orderVisibility
                                ->where('orders.user_id', $userId)
                                ->orWhere('orders.seller_id', $userId);
                        });
                });
        });
    }

    private function summarizeManualPaymentRequests(Builder $query): array
    {
        $summary = [
            'total' => [
                'count' => 0,
                'amount' => 0.0,
                'amounts' => [],
            ],
            'statuses' => [
                ManualPaymentRequest::STATUS_PENDING => [
                    'count' => 0,
                    'amount' => 0.0,
                    'amounts' => [],
                ],
                ManualPaymentRequest::STATUS_UNDER_REVIEW => [
                    'count' => 0,
                    'amount' => 0.0,
                    'amounts' => [],
                ],
                ManualPaymentRequest::STATUS_APPROVED => [
                    'count' => 0,
                    'amount' => 0.0,
                    'amounts' => [],
                ],
                ManualPaymentRequest::STATUS_REJECTED => [
                    'count' => 0,
                    'amount' => 0.0,
                    'amounts' => [],
                ],
            ],
        ];

        $rows = (clone $query)
            ->cloneWithout(['columns', 'orders'])
            ->selectRaw('COALESCE(manual_payment_requests.status, ?) as status', [ManualPaymentRequest::STATUS_PENDING])
            ->selectRaw('COALESCE(UPPER(manual_payment_requests.currency), \'\') as currency')
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('COALESCE(SUM(manual_payment_requests.amount), 0) as total_amount')
            ->groupBy('status', 'currency')
            ->get();

        foreach ($rows as $row) {
            $status = $row->status ?? ManualPaymentRequest::STATUS_PENDING;
            if (!array_key_exists($status, $summary['statuses'])) {
                $summary['statuses'][$status] = [
                    'count' => 0,
                    'amount' => 0.0,
                    'amounts' => [],
                ];
            }

            $count = (int) $row->total_count;
            $amount = (float) $row->total_amount;
            $currency = is_string($row->currency) && $row->currency !== ''
                ? strtoupper($row->currency)
                : null;

            $summary['statuses'][$status]['count'] += $count;
            $summary['total']['count'] += $count;

            if ($currency !== null) {
                $statusAmounts = &$summary['statuses'][$status]['amounts'];
                $statusAmounts[$currency] = ($statusAmounts[$currency] ?? 0.0) + $amount;
                $summary['statuses'][$status]['amount'] = ($summary['statuses'][$status]['amount'] ?? 0.0) + $amount;

                $totalAmounts = &$summary['total']['amounts'];
                $totalAmounts[$currency] = ($totalAmounts[$currency] ?? 0.0) + $amount;
                $summary['total']['amount'] = ($summary['total']['amount'] ?? 0.0) + $amount;
                unset($statusAmounts, $totalAmounts);
            }
        }

        return $summary;
    }



    private function extractIntegerFromKeys(array $input, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }

            $value = $input[$key];

            if (is_numeric($value)) {
                $intValue = (int) $value;

                return $intValue > 0 ? $intValue : null;
            }
        }

        return null;
    }

    private function resolveManualPaymentDepartment(
        ?string $payableType,
        mixed $payableId,
        ?ManualPaymentRequest $existingManualPaymentRequest
    ): ?string {
        if (! ManualPaymentRequest::isOrderPayableType($payableType)) {
            return null;
        }

        $orderId = is_numeric($payableId) ? (int) $payableId : null;

        if ($orderId !== null) {
            $department = Order::query()->whereKey($orderId)->value('department');
            $normalized = $this->normalizeDepartmentValue($department);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        if ($existingManualPaymentRequest !== null) {
            $normalized = $this->normalizeDepartmentValue($existingManualPaymentRequest->department);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }


    private function normalizeDepartmentValue(mixed $department): ?string
    {
        if (! is_string($department)) {
            return null;
        }

        $trimmed = trim($department);

        return $trimmed === '' ? null : $trimmed;
    }






    

    private function chatConversationsSupportsColumn(string $column): bool
    {
        static $columnSupport = [];

        if (!array_key_exists($column, $columnSupport)) {
            $columnSupport[$column] = Schema::hasTable('chat_conversations')
                && Schema::hasColumn('chat_conversations', $column);
        }

        return $columnSupport[$column];
    }

    private function resolveConversationAssignedAgent(ItemOffer $itemOffer, array $delegates): ?int
    
    
    {
        if (empty($delegates)) {
            return null;
        }

        $possibleOwners = array_filter([
            $itemOffer->seller_id,
            $itemOffer->item?->user_id,
        ]);

        foreach ($possibleOwners as $ownerId) {
            if (in_array($ownerId, $delegates, true)) {
                return $ownerId;
            }
        }

        return $delegates[0] ?? null;
    }

    private function syncConversationDepartmentAndAssignment(Chat $conversation, ?string $department, ?int $assignedAgentId): bool
    {
        $updated = false;

        if (
            $department &&
            $this->chatConversationsSupportsColumn('department') &&
            empty($conversation->department)
        ) {
            
            
            $conversation->department = $department;
            $updated = true;
        }

        if (
            $assignedAgentId &&
            $this->chatConversationsSupportsColumn('assigned_to') &&
            empty($conversation->assigned_to)
        ) {
            
            
            $conversation->assigned_to = $assignedAgentId;
            $updated = true;
        }

        if ($updated) {
            $conversation->save();
        }

        return $updated;
    }

    private function handleSupportEscalation(Chat $conversation, ChatMessage $chatMessage, ?string $department, User $reporter): void
    {
        if (empty($department)) {
            return;
        }

        if (!empty($conversation->assigned_to)) {
            $this->notifySupportAgent($conversation, (int) $conversation->assigned_to, $chatMessage, $department, $reporter);

            return;
        }

        $this->openSupportTicket($conversation, $department, $chatMessage, $reporter);
    }

    private function notifySupportAgent(Chat $conversation, int $agentId, ChatMessage $chatMessage, string $department, User $reporter): void
    {
        $tokens = UserFcmToken::query()
            ->where('user_id', $agentId)
            ->pluck('fcm_token')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($tokens)) {
            return;
        }

        $senderName = $chatMessage->sender?->name ?? $reporter->name ?? __('أ¢â€‌ع©ط£آ أ¢â€¢ع¾أ¢â€‌â€ڑأ¢â€¢ع¾ط·آ²أ¢â€¢ع¾ط¢آ«أ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط£آ ');
        $messagePreview = $chatMessage->message ?? __('أ¢â€¢ع¾ط·آ²أ¢â€‌ع©ط£آ  أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾أ¢â€‌â€ڑأ¢â€¢ع¾ط·آ²أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط£آ  أ¢â€¢ع¾أ¢â€“â€™أ¢â€¢ع¾أ¢â€‌â€ڑأ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ± أ¢â€¢ع¾ط·آ´أ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط£آ¨أ¢â€¢ع¾ط¢آ»أ¢â€¢ع¾ط·آ±.');

        $response = NotificationService::sendFcmNotification(
            $tokens,
            __('أ¢â€‌ع©ط£آ أ¢â€¢ع¾ط·آµأ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾ط¢آ»أ¢â€¢ع¾ط·آ³أ¢â€¢ع¾ط·آ± أ¢â€¢ع¾ط·آ´أ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط£آ¨أ¢â€¢ع¾ط¢آ»أ¢â€¢ع¾ط·آ± أ¢â€‌ع©ط£آ أ¢â€‌ع©ط¢â€  :name', ['name' => $senderName]),
            Str::limit($messagePreview, 120),
            'support_chat_assignment',
            [
                'conversation_id' => $conversation->id,
                'item_offer_id' => $conversation->item_offer_id,
                'department' => $department,
                'assigned_to' => $agentId,
                'message_id' => $chatMessage->id,
                'message_type' => $chatMessage->message_type,
            ]
        );

        if (is_array($response) && ($response['error'] ?? false)) {
            \Log::warning('ApiController: Failed to notify support agent via FCM', [
                'agent_id' => $agentId,
                'conversation_id' => $conversation->id,
                'message' => $response['message'] ?? null,
                'code' => $response['code'] ?? null,
            ]);
        }

    }

    private function openSupportTicket(Chat $conversation, string $department, ChatMessage $chatMessage, User $reporter): DepartmentTicket
    {
        return DepartmentTicket::firstOrCreate(
            [
                'chat_conversation_id' => $conversation->id,
                'department' => $department,
                'status' => DepartmentTicket::STATUS_OPEN,
            ],
            [
                'subject' => sprintf('أ¢â€‌ع©ط£آ أ¢â€¢ع¾ط·آµأ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾ط¢آ»أ¢â€¢ع¾ط·آ³أ¢â€¢ع¾ط·آ± #%d أ¢â€¢ع¾ط·آ²أ¢â€‌ع©ط¢â€ أ¢â€¢ع¾ط·آ²أ¢â€¢ع¾أ¢â€¢â€¢أ¢â€¢ع¾أ¢â€“â€™ أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ²أ¢â€¢ع¾أ¢â€¢آ£أ¢â€‌ع©ط£آ¨أ¢â€‌ع©ط£آ¨أ¢â€‌ع©ط¢â€ ', $conversation->id),
                'description' => $this->buildSupportTicketDescription($chatMessage, $reporter),
                'reporter_id' => $reporter->id,
            ]
        );
    }

    private function buildSupportTicketDescription(ChatMessage $chatMessage, User $reporter): string
    {
        $senderName = $chatMessage->sender?->name ?? $reporter->name ?? __('أ¢â€‌ع©ط£آ أ¢â€¢ع¾أ¢â€‌â€ڑأ¢â€¢ع¾ط·آ²أ¢â€¢ع¾ط¢آ«أ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط£آ ');
        $messagePreview = $chatMessage->message
            ? Str::limit($chatMessage->message, 160)
            : __('أ¢â€¢ع¾ط·آ²أ¢â€‌ع©ط£آ  أ¢â€‌ع©ط¢ظ¾أ¢â€¢ع¾ط·آ²أ¢â€¢ع¾ط·آµ أ¢â€‌ع©ط£آ أ¢â€¢ع¾ط·آµأ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾ط¢آ»أ¢â€¢ع¾ط·آ³أ¢â€¢ع¾ط·آ± أ¢â€¢ع¾ط·آ´أ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط£آ¨أ¢â€¢ع¾ط¢آ»أ¢â€¢ع¾ط·آ± أ¢â€¢ع¾ط·آ°أ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط£ع¾أ¢â€‌ع©ط¢â€  أ¢â€¢ع¾أ¢â€“â€™أ¢â€¢ع¾أ¢â€‌â€ڑأ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ± أ¢â€‌ع©ط¢â€ أ¢â€¢ع¾أ¢â€¢طŒأ¢â€‌ع©ط£آ¨أ¢â€¢ع¾ط·آ±.');

        return sprintf(
            'أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط£آ أ¢â€¢ع¾أ¢â€‌â€ڑأ¢â€¢ع¾ط·آ²أ¢â€¢ع¾ط¢آ«أ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط£آ  %s أ¢â€¢ع¾ط·آ«أ¢â€‌ع©ط¢â€ أ¢â€¢ع¾أ¢â€‌آ¤أ¢â€¢ع¾ط·آ« أ¢â€‌ع©ط£آ أ¢â€¢ع¾ط·آµأ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾ط¢آ»أ¢â€¢ع¾ط·آ³أ¢â€¢ع¾ط·آ± أ¢â€¢ع¾ط·آ´أ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط£آ¨أ¢â€¢ع¾ط¢آ»أ¢â€¢ع¾ط·آ±. أ¢â€¢ع¾ط·ع¾أ¢â€¢ع¾ط¢آ«أ¢â€¢ع¾أ¢â€“â€™ أ¢â€¢ع¾أ¢â€“â€™أ¢â€¢ع¾أ¢â€‌â€ڑأ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ±: %s',
            $senderName,
            $messagePreview
        );
    }


    private function extractMessageIdsFromRequest(Request $request): Collection
    {
        $ids = collect();

        $bulkIds = $request->input('message_ids');

        if (is_array($bulkIds)) {
            foreach ($bulkIds as $id) {
                if (is_numeric($id)) {
                    $ids->push((int) $id);
                }
            }
        }

        if ($request->filled('message_id') && is_numeric($request->input('message_id'))) {
            $ids->push((int) $request->input('message_id'));
        }

        return $ids
            ->filter(static fn ($id) => is_int($id) && $id > 0)
            ->unique()
            ->values();
    }

    private function resolveAuthorizedMessages(Collection $messageIds, User $user, ?int $conversationId = null): Collection
    {
        if ($messageIds->isEmpty()) {
            return collect();
        }

        $messages = ChatMessage::with('conversation')
            ->whereIn('id', $messageIds->all())
            ->get()
            ->keyBy(static fn (ChatMessage $message) => (int) $message->id);

        if ($messages->count() !== $messageIds->count()) {
            ResponseService::errorResponse('One or more messages not found', null, 404);
        }

        if ($conversationId !== null) {
            $mismatched = $messages->first(static function (ChatMessage $message) use ($conversationId) {
                return (int) $message->conversation_id !== $conversationId;
            });

            if ($mismatched) {
                ResponseService::validationError('One or more messages do not belong to the provided conversation.');
            }
        }

        $conversationIds = $messages->pluck('conversation_id')
            ->filter()
            ->map(static fn ($id) => (int) $id)
            ->unique();

        if ($conversationIds->isNotEmpty()) {
            $authorizedConversationIds = Chat::whereIn('id', $conversationIds->all())
                ->whereHas('participants', function ($query) use ($user) {
                    $query->where('users.id', $user->id);
                })
                ->pluck('id')
                ->map(static fn ($id) => (int) $id);

            $unauthorized = $conversationIds->diff($authorizedConversationIds);

            if ($unauthorized->isNotEmpty()) {
                ResponseService::errorResponse('You are not allowed to update this message', null, 403);
            }
        }

        return $messages;
    }

    private function formatMessageUpdateResponse(Collection $messages)
    {
        if ($messages->count() <= 1) {
            return $messages->first();
        }

        return $messages->values();
    }


    private function isGeoDisabledCategory(int $categoryId): bool
    {
        return in_array($categoryId, $this->geoDisabledCategoryIds(), true);
    }

    private function isProductLinkRequiredCategory(int $categoryId): bool
    {
        return $this->shouldRequireProductLink($categoryId);
    }


    private function geoDisabledCategoryIds(): array
    {
        if ($this->geoDisabledCategoryCache !== null) {
            return $this->geoDisabledCategoryCache;
        }

        $raw = CachingService::getSystemSettings('geo_disabled_categories');
        $ids = $this->parseCategoryIdList($raw);

        $departmentService = app(DepartmentReportService::class);
        $alwaysDisabled = array_merge(
            $departmentService->resolveCategoryIds(DepartmentReportService::DEPARTMENT_SHEIN),
            $departmentService->resolveCategoryIds(DepartmentReportService::DEPARTMENT_COMPUTER),
            $departmentService->resolveCategoryIds(DepartmentReportService::DEPARTMENT_STORE),
        );

        $normalized = [];


        foreach (array_merge($ids, $alwaysDisabled, [295]) as $value) {
            if (! is_int($value)) {
                if (is_numeric($value)) {
                    $value = (int) $value;
                } else {
                    continue;
                }


            }

             if ($value > 0) {
                $normalized[$value] = $value;
            }
        }
        return $this->geoDisabledCategoryCache = array_values($normalized);

    }


    private function productLinkRequiredCategoryIds(): array
    {
        if ($this->productLinkRequiredCategoryCache !== null) {
            return $this->productLinkRequiredCategoryCache;
        }

        $sections = $this->productLinkRequiredSections();


        if ($sections === []) {
            return $this->productLinkRequiredCategoryCache = [];
        }

        $ids = [];


        foreach ($sections as $section) {
            $ids = array_merge(
                $ids,
                $this->departmentReportService->resolveCategoryIds($section)
            );
        }

        $ids = array_filter($ids, static fn ($id) => is_int($id) && $id > 0);

        return $this->productLinkRequiredCategoryCache = array_values(array_unique($ids));
    }


    private function shouldRequireProductLink(?int $categoryId): bool
    {
        if ($categoryId === null) {
            return false;
        }

        if ($this->isGeoDisabledCategory($categoryId)) {
            return false;
        }


        $section = $this->resolveSectionByCategoryId($categoryId);

        if ($section === null) {
            return false;
        }

        $normalizedSection = strtolower($section);

        if (in_array($normalizedSection, [
            DepartmentReportService::DEPARTMENT_SHEIN,
            DepartmentReportService::DEPARTMENT_COMPUTER,
            DepartmentReportService::DEPARTMENT_STORE,
        ], true)) {
            return false;
        }

        return in_array($normalizedSection, $this->productLinkRequiredSections(), true);
    
    }


    private function productLinkRequiredSections(): array
    {
        if ($this->productLinkRequiredSectionCache !== null) {
            return $this->productLinkRequiredSectionCache;
        }

        $raw = CachingService::getSystemSettings('product_link_required_categories');
        $sections = [];

        $interfaceMap = array_change_key_case(config('cart.interface_map', []), CASE_LOWER);
        $interfaceMap = array_map(
            static fn ($value) => is_string($value) ? strtolower($value) : $value,
            $interfaceMap
        );
        $validSections = array_map('strtolower', config('cart.departments', []));

        $consume = function (mixed $value) use (&$sections, &$consume, $interfaceMap, $validSections): void {
            if ($value === null) {
                return;
            }

            if (is_int($value) || is_float($value)) {
                $section = $this->resolveSectionByCategoryId((int) $value);
                if ($section !== null && strtolower($section) === DepartmentReportService::DEPARTMENT_SHEIN) {
                    $sections[] = DepartmentReportService::DEPARTMENT_SHEIN;
                }
                return;
            }

            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed === '') {
                    return;
                }

                try {
                    $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
                    if ($decoded !== null && $decoded !== $trimmed) {
                        $consume($decoded);
                        return;
                    }
                } catch (Throwable) {
                    // ignore malformed JSON strings
                }

                if (preg_match_all('/\d+/', $trimmed, $matches) && isset($matches[0])) {
                    foreach ($matches[0] as $match) {
                        $consume((int) $match);
                    }
                }

                $normalized = strtolower($trimmed);
                if (isset($interfaceMap[$normalized]) && is_string($interfaceMap[$normalized])) {
                    $normalized = strtolower($interfaceMap[$normalized]);
                }

                if (in_array($normalized, $validSections, true)) {
                    $sections[] = $normalized;
                }
                return;
            }

            if (is_iterable($value)) {
                foreach ($value as $entry) {
                    $consume($entry);
                }
            }
        };

        $consume($raw);

        $sections = array_values(array_unique(array_filter(
            $sections,
            static fn ($section) => is_string($section) && in_array($section, $validSections, true)
        )));

        if ($sections === []) {
            $sections = [DepartmentReportService::DEPARTMENT_SHEIN];
        }

        return $this->productLinkRequiredSectionCache = $sections;
    }




    private function parseCategoryIdList(mixed $raw): array
    {
        $resolved = [];

        $consume = function (mixed $value) use (&$resolved, &$consume): void {
            if ($value === null) {
                return;
            }

            if (is_int($value) || is_float($value)) {
                $int = (int) $value;
                if ($int > 0) {
                    $resolved[] = $int;
                }
                return;
            }

            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed === '') {
                    return;
                }

                try {
                    $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
                    if ($decoded !== null && $decoded !== $trimmed) {
                        $consume($decoded);
                        return;
                    }
                } catch (Throwable) {
                    // ignore malformed JSON strings
                }

                if (preg_match_all('/\d+/', $trimmed, $matches)) {
                    foreach ($matches[0] as $match) {
                        $int = (int) $match;
                        if ($int > 0) {
                            $resolved[] = $int;
                        }
                    }
                }
                return;
            }

            if (is_iterable($value)) {
                foreach ($value as $entry) {
                    $consume($entry);
                }
            }
        };

        $consume($raw);

        return array_values(array_unique(array_filter(
            $resolved,
            static fn ($id) => is_int($id) && $id > 0
        )));
    }


    private function resolveInterfaceSectionForCategory(?int $categoryId): ?string
    {
        if (empty($categoryId)) {
            return null;
        }

        if ($this->interfaceSectionCategoryCache === null) {
            $this->interfaceSectionCategoryCache = [];

            $sectionTypes = InterfaceSectionService::allowedSectionTypes(includeLegacy: true);
            foreach ($sectionTypes as $sectionType) {
                $categoryIds = InterfaceSectionService::categoryIdsForSection($sectionType);
                if (! is_array($categoryIds) || $categoryIds === []) {
                    continue;
                }
                foreach ($categoryIds as $id) {
                    if (! is_int($id)) {
                        continue;
                    }
                    $this->interfaceSectionCategoryCache[$id] = $sectionType;
                }
            }
        }

        return $this->interfaceSectionCategoryCache[$categoryId] ?? null;
    }

    private function resolveSectionByCategoryId(?int $categoryId): ?string
    {
        if (empty($categoryId)) {
            return null;
        }

        foreach ($this->getDepartmentCategoryMap() as $section => $categoryIds) {
            if (in_array($categoryId, $categoryIds, true)) {
                return $section;
            }
        }

        return null;
    }

    /**
     * Resolve the reporting department for a given category id.
     */
    private function resolveReportDepartment(?int $categoryId): ?string
    {
        if (empty($categoryId)) {
            return null;
        }

        $map = [
            DepartmentReportService::DEPARTMENT_SHEIN =>
                $this->departmentReportService->resolveCategoryIds(DepartmentReportService::DEPARTMENT_SHEIN),
            DepartmentReportService::DEPARTMENT_COMPUTER =>
                $this->departmentReportService->resolveCategoryIds(DepartmentReportService::DEPARTMENT_COMPUTER),
            DepartmentReportService::DEPARTMENT_STORE =>
                $this->departmentReportService->resolveCategoryIds(DepartmentReportService::DEPARTMENT_STORE),
            DepartmentReportService::DEPARTMENT_SERVICES =>
                $this->departmentReportService->resolveCategoryIds(DepartmentReportService::DEPARTMENT_SERVICES),
        ];

        foreach ($map as $department => $categoryIds) {
            if (in_array($categoryId, $categoryIds, true)) {
                return $department;
            }
        }

        return null;
    }


    private function resolveInitialItemStatus(User $user, ?string $section): string
    {
        if ($this->shouldAutoApproveSection($section) || $this->shouldSkipReviewForVerifiedUser($user)) {
            return 'approved';
        }

        return 'review';
    }

    private function shouldSkipReviewForVerifiedUser(User $user): bool
    {
        if (! $this->hasVerifiedIndividualPrivileges($user)) {
            return false;
        }

        $limit = (int) config('items.auto_approve_verified_max_per_hour', 10);

        if ($limit > 0) {
            $recentCount = Item::query()
                ->where('user_id', $user->id)
                ->where('created_at', '>=', now()->subHour())
                ->count();

            if ($recentCount >= $limit) {
                return false;
            }
        }

        return true;
    }

    private function hasVerifiedIndividualPrivileges(User $user): bool
    {
        $eligibleTypes = [
            User::ACCOUNT_TYPE_CUSTOMER,
            User::ACCOUNT_TYPE_REAL_ESTATE,
        ];

        return in_array($user->account_type, $eligibleTypes, true) && $user->hasActiveVerification();
    }

    private function shouldAutoApproveSection(?string $section): bool
    {
        if ($section === null) {
            return false;
        }

        $autoApproved = array_filter(
            (array) config('delegates.auto_approve_departments', [])
        );

        return in_array($section, $autoApproved, true);
    }


    private function getDepartmentCategoryMap(): array
    {
        if ($this->departmentCategoryMap === []) {
            $this->departmentCategoryMap = [
                DepartmentReportService::DEPARTMENT_SHEIN => $this->departmentReportService->resolveCategoryIds(DepartmentReportService::DEPARTMENT_SHEIN),
                DepartmentReportService::DEPARTMENT_COMPUTER => $this->departmentReportService->resolveCategoryIds(DepartmentReportService::DEPARTMENT_COMPUTER),
                DepartmentReportService::DEPARTMENT_STORE => $this->departmentReportService->resolveCategoryIds(DepartmentReportService::DEPARTMENT_STORE),

            ];
        }

        return $this->departmentCategoryMap;
    }

    /**
     * Filter the item select columns to include only those that are available on the table.
     */
    private function filterItemSelectColumns(array $columns): array
    {
        $availability = $this->getItemColumnAvailability();

        return array_values(array_filter($columns, static function ($column) use ($availability) {
            $expression = $column;

            $aliasPosition = stripos($expression, ' as ');
            if ($aliasPosition !== false) {
                $expression = substr($expression, 0, $aliasPosition);
            }

            $expression = trim($expression);

            if ($expression === '') {
                return false;
            }

            if (! str_contains($expression, '.')) {
                return true;
            }

            [$table, $columnName] = explode('.', $expression, 2);

            if (strcasecmp($table, 'items') !== 0) {
                return true;
            }

            return isset($availability[$columnName]);
        }));
    }
  

    /**
     * Generate a fallback email address for phone-based signups.
     */
    private function generatePhoneSignupEmail(?string $countryCode, ?string $mobile): string
    {
        $numericCountryCode = preg_replace('/\D+/', '', (string) $countryCode);
        $numericMobile = preg_replace('/\D+/', '', (string) $mobile);

        $identifier = trim($numericCountryCode . $numericMobile);

        if ($identifier === '') {
            $identifier = 'user_' . Str::uuid()->toString();
        } else {
            $identifier = 'user_' . $identifier;
        }

        $baseIdentifier = Str::lower($identifier);
        $domain = 'phone.marib.app';
        $email = $baseIdentifier . '@' . $domain;

        if (! User::where('email', $email)->exists()) {
            return $email;
        }

        do {
            $email = $baseIdentifier . '_' . Str::lower(Str::random(6)) . '@' . $domain;
        } while (User::where('email', $email)->exists());

        return $email;
    }



    /**
     * Retrieve and cache the available columns on the items table.
     */
    private function getItemColumnAvailability(): array
    {
        if (self::$itemColumnAvailability !== null) {
            return self::$itemColumnAvailability;
        }

        $columns = [];

        try {
            if (Schema::hasTable('items')) {
                foreach (Schema::getColumnListing('items') as $column) {
                    $columns[$column] = true;
                }
            }
        } catch (Throwable $exception) {
            Log::warning('Failed to inspect items table columns.', [
                'exception' => $exception->getMessage(),
            ]);
        }

        return self::$itemColumnAvailability = $columns;
    }

    /**
     * Expand the provided category ids to include all their descendant ids.
     *
     * @param array<int> $categoryIds
     * @return array<int>
     */
    private function expandCategoryIdsWithDescendants(array $categoryIds): array
    {
        $expanded = [];

        foreach ($categoryIds as $id) {
            $intId = is_numeric($id) ? (int) $id : null;

            if ($intId === null || $intId <= 0) {
                continue;
            }

            foreach ($this->collectCategoryTreeIds($intId) as $treeId) {
                $expanded[$treeId] = $treeId;
            }
        }

        return array_values($expanded);
    }

    /**
     * Return the given category id plus all its descendants.
     */
    protected function collectCategoryTreeIds(int $rootCategoryId): array
    {
        $categories = Category::select('id', 'parent_category_id')->get();

        $children = [];
        foreach ($categories as $category) {
            $parentKey = $category->parent_category_id ?? 0;
            $children[$parentKey][] = $category->id;
        }

        $ids = [];
        $stack = [$rootCategoryId];

        while (! empty($stack)) {
            $current = array_pop($stack);

            if (in_array($current, $ids, true)) {
                continue;
            }

            $ids[] = $current;

            if (isset($children[$current])) {
                foreach ($children[$current] as $childId) {
                    $stack[] = $childId;
                }
            }
        }

        return $ids;
    }
}






