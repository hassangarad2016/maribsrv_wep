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

    use \App\Http\Controllers\Api\Sections\Core\Construct\ConstructTrait;
    use \App\Http\Controllers\Api\Sections\Core\InterfaceTypes\InterfaceTypesTrait;

    use \App\Http\Controllers\Api\Sections\Settings\BuildSocialLinks\BuildSocialLinksTrait;
    use \App\Http\Controllers\Api\Sections\Settings\GetCustomFields\GetCustomFieldsTrait;
    use \App\Http\Controllers\Api\Sections\Settings\GetLanguages\GetLanguagesTrait;
    use \App\Http\Controllers\Api\Sections\Settings\GetSystemSettings\GetSystemSettingsTrait;
    use \App\Http\Controllers\Api\Sections\Settings\HydrateSettingValues\HydrateSettingValuesTrait;
    use \App\Http\Controllers\Api\Sections\Settings\NormalizeSettingNames\NormalizeSettingNamesTrait;
    use \App\Http\Controllers\Api\Sections\Settings\SeoSettings\SeoSettingsTrait;
    use \App\Http\Controllers\Api\Sections\Settings\SocialLinkSettingKeys\SocialLinkSettingKeysTrait;

    use \App\Http\Controllers\Api\Sections\Auth\BuildPendingSignupPayload\BuildPendingSignupPayloadTrait;
    use \App\Http\Controllers\Api\Sections\Auth\CompleteRegistration\CompleteRegistrationTrait;
    use \App\Http\Controllers\Api\Sections\Auth\FinalizePendingSignup\FinalizePendingSignupTrait;
    use \App\Http\Controllers\Api\Sections\Auth\HandleDeferredPhoneSignup\HandleDeferredPhoneSignupTrait;
    use \App\Http\Controllers\Api\Sections\Auth\NormalizePhoneNumber\NormalizePhoneNumberTrait;
    use \App\Http\Controllers\Api\Sections\Auth\SendOtp\SendOtpTrait;
    use \App\Http\Controllers\Api\Sections\Auth\UpdatePassword\UpdatePasswordTrait;
    use \App\Http\Controllers\Api\Sections\Auth\UserLogin\UserLoginTrait;
    use \App\Http\Controllers\Api\Sections\Auth\UserSignup\UserSignupTrait;
    use \App\Http\Controllers\Api\Sections\Auth\VerifyOtp\VerifyOtpTrait;

    use \App\Http\Controllers\Api\Sections\Profile\DeleteUser\DeleteUserTrait;
    use \App\Http\Controllers\Api\Sections\Profile\GetUserProfileStats\GetUserProfileStatsTrait;
    use \App\Http\Controllers\Api\Sections\Profile\GetUsersByAccountType\GetUsersByAccountTypeTrait;
    use \App\Http\Controllers\Api\Sections\Profile\SaveUserLocation\SaveUserLocationTrait;
    use \App\Http\Controllers\Api\Sections\Profile\UpdateProfile\UpdateProfileTrait;

    use \App\Http\Controllers\Api\Sections\Packages\AssignFreePackage\AssignFreePackageTrait;
    use \App\Http\Controllers\Api\Sections\Packages\GetLimits\GetLimitsTrait;
    use \App\Http\Controllers\Api\Sections\Packages\GetPackage\GetPackageTrait;

    use \App\Http\Controllers\Api\Sections\Items\AddItem\AddItemTrait;
    use \App\Http\Controllers\Api\Sections\Items\BuildStorePolicySummary\BuildStorePolicySummaryTrait;
    use \App\Http\Controllers\Api\Sections\Items\CollectCategoryTreeIds\CollectCategoryTreeIdsTrait;
    use \App\Http\Controllers\Api\Sections\Items\DeleteItem\DeleteItemTrait;
    use \App\Http\Controllers\Api\Sections\Items\ExpandCategoryIdsWithDescendants\ExpandCategoryIdsWithDescendantsTrait;
    use \App\Http\Controllers\Api\Sections\Items\FilterItemSelectColumns\FilterItemSelectColumnsTrait;
    use \App\Http\Controllers\Api\Sections\Items\FormatSummaryResult\FormatSummaryResultTrait;
    use \App\Http\Controllers\Api\Sections\Items\GeoDisabledCategoryIds\GeoDisabledCategoryIdsTrait;
    use \App\Http\Controllers\Api\Sections\Items\GetDepartmentCategoryMap\GetDepartmentCategoryMapTrait;
    use \App\Http\Controllers\Api\Sections\Items\GetItem\GetItemTrait;
    use \App\Http\Controllers\Api\Sections\Items\GetItemBuyerList\GetItemBuyerListTrait;
    use \App\Http\Controllers\Api\Sections\Items\GetItemColumnAvailability\GetItemColumnAvailabilityTrait;
    use \App\Http\Controllers\Api\Sections\Items\GetSeller\GetSellerTrait;
    use \App\Http\Controllers\Api\Sections\Items\HasVerifiedIndividualPrivileges\HasVerifiedIndividualPrivilegesTrait;
    use \App\Http\Controllers\Api\Sections\Items\IsGeoDisabledCategory\IsGeoDisabledCategoryTrait;
    use \App\Http\Controllers\Api\Sections\Items\IsProductLinkRequiredCategory\IsProductLinkRequiredCategoryTrait;
    use \App\Http\Controllers\Api\Sections\Items\ParseCategoryIdList\ParseCategoryIdListTrait;
    use \App\Http\Controllers\Api\Sections\Items\ProductLinkRequiredCategoryIds\ProductLinkRequiredCategoryIdsTrait;
    use \App\Http\Controllers\Api\Sections\Items\ProductLinkRequiredSections\ProductLinkRequiredSectionsTrait;
    use \App\Http\Controllers\Api\Sections\Items\RenewItem\RenewItemTrait;
    use \App\Http\Controllers\Api\Sections\Items\ResolveInitialItemStatus\ResolveInitialItemStatusTrait;
    use \App\Http\Controllers\Api\Sections\Items\ResolveInterfaceSectionForCategory\ResolveInterfaceSectionForCategoryTrait;
    use \App\Http\Controllers\Api\Sections\Items\ResolveReportDepartment\ResolveReportDepartmentTrait;
    use \App\Http\Controllers\Api\Sections\Items\ResolveSectionByCategoryId\ResolveSectionByCategoryIdTrait;
    use \App\Http\Controllers\Api\Sections\Items\SetItemTotalClick\SetItemTotalClickTrait;
    use \App\Http\Controllers\Api\Sections\Items\ShouldAutoApproveSection\ShouldAutoApproveSectionTrait;
    use \App\Http\Controllers\Api\Sections\Items\ShouldRequireProductLink\ShouldRequireProductLinkTrait;
    use \App\Http\Controllers\Api\Sections\Items\ShouldSkipReviewForVerifiedUser\ShouldSkipReviewForVerifiedUserTrait;
    use \App\Http\Controllers\Api\Sections\Items\UpdateItem\UpdateItemTrait;
    use \App\Http\Controllers\Api\Sections\Items\UpdateItemStatus\UpdateItemStatusTrait;

    use \App\Http\Controllers\Api\Sections\Categories\GetParentCategoryTree\GetParentCategoryTreeTrait;
    use \App\Http\Controllers\Api\Sections\Categories\GetSubCategories\GetSubCategoriesTrait;

    use \App\Http\Controllers\Api\Sections\FeaturedAds\GetFeaturedAdsCount\GetFeaturedAdsCountTrait;
    use \App\Http\Controllers\Api\Sections\FeaturedAds\GetFeaturedSection\GetFeaturedSectionTrait;
    use \App\Http\Controllers\Api\Sections\FeaturedAds\GetFeaturedSections\GetFeaturedSectionsTrait;
    use \App\Http\Controllers\Api\Sections\FeaturedAds\MakeFeaturedItem\MakeFeaturedItemTrait;
    use \App\Http\Controllers\Api\Sections\FeaturedAds\UnfeatureAd\UnfeatureAdTrait;

    use \App\Http\Controllers\Api\Sections\Favourites\GetFavouriteItem\GetFavouriteItemTrait;
    use \App\Http\Controllers\Api\Sections\Favourites\ManageFavourite\ManageFavouriteTrait;

    use \App\Http\Controllers\Api\Sections\Slider\GetSlider\GetSliderTrait;
    use \App\Http\Controllers\Api\Sections\Slider\RecordSliderClick\RecordSliderClickTrait;
    use \App\Http\Controllers\Api\Sections\Slider\ResolveSliderSessionId\ResolveSliderSessionIdTrait;

    use \App\Http\Controllers\Api\Sections\Reports\AddReports\AddReportsTrait;
    use \App\Http\Controllers\Api\Sections\Reports\GetReportReasons\GetReportReasonsTrait;

    use \App\Http\Controllers\Api\Sections\Payments\GetPaymentIntent\GetPaymentIntentTrait;
    use \App\Http\Controllers\Api\Sections\Payments\GetPaymentSettings\GetPaymentSettingsTrait;
    use \App\Http\Controllers\Api\Sections\Payments\GetPaymentTransactions\GetPaymentTransactionsTrait;
    use \App\Http\Controllers\Api\Sections\Payments\InAppPurchase\InAppPurchaseTrait;

    use \App\Http\Controllers\Api\Sections\Wallet\ApplyWalletTransactionFilter\ApplyWalletTransactionFilterTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\BuildDirectionalWalletTransferKey\BuildDirectionalWalletTransferKeyTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\BuildManualPaymentWalletIdempotencyKey\BuildManualPaymentWalletIdempotencyKeyTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\BuildWalletFilterPayload\BuildWalletFilterPayloadTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\BuildWalletIdempotencyKey\BuildWalletIdempotencyKeyTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\BuildWalletTransferIdempotencyKey\BuildWalletTransferIdempotencyKeyTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\BuildWalletTransferMeta\BuildWalletTransferMetaTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\DebitWalletTransaction\DebitWalletTransactionTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\EnsureWalletAccountCurrency\EnsureWalletAccountCurrencyTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\EnsureWalletDebit\EnsureWalletDebitTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\FieldRulesContainRequired\FieldRulesContainRequiredTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\FindOrCreateWalletTransaction\FindOrCreateWalletTransactionTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\FindWalletPaymentTransaction\FindWalletPaymentTransactionTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\GetWalletCurrencyCode\GetWalletCurrencyCodeTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\GetWalletWithdrawalMethods\GetWalletWithdrawalMethodsTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\MaskMobileNumber\MaskMobileNumberTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\NormalizeCurrencyCode\NormalizeCurrencyCodeTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\NormalizeWithdrawalMethodFields\NormalizeWithdrawalMethodFieldsTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\PerformWalletTransfer\PerformWalletTransferTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\ResolveWalletTransaction\ResolveWalletTransactionTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\ShowWalletWithdrawalRequest\ShowWalletWithdrawalRequestTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\StoreWalletWithdrawalRequest\StoreWalletWithdrawalRequestTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\TransferRequest\TransferRequestTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\ValidateWithdrawalMeta\ValidateWithdrawalMetaTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\WalletFilterLabel\WalletFilterLabelTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\WalletRecipient\WalletRecipientTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\WalletRecipientLookup\WalletRecipientLookupTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\WalletSummary\WalletSummaryTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\WalletTransactions\WalletTransactionsTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\WalletWithdrawalOptions\WalletWithdrawalOptionsTrait;
    use \App\Http\Controllers\Api\Sections\Wallet\WalletWithdrawalRequests\WalletWithdrawalRequestsTrait;

    use \App\Http\Controllers\Api\Sections\ManualPayments\AppendManualPaymentReceiptMeta\AppendManualPaymentReceiptMetaTrait;
    use \App\Http\Controllers\Api\Sections\ManualPayments\ApplyManualPaymentRequestVisibilityScope\ApplyManualPaymentRequestVisibilityScopeTrait;
    use \App\Http\Controllers\Api\Sections\ManualPayments\BuildManualPaymentMetaUpdates\BuildManualPaymentMetaUpdatesTrait;
    use \App\Http\Controllers\Api\Sections\ManualPayments\CleanupManualPaymentMeta\CleanupManualPaymentMetaTrait;
    use \App\Http\Controllers\Api\Sections\ManualPayments\ExpandManualPaymentGatewayAliases\ExpandManualPaymentGatewayAliasesTrait;
    use \App\Http\Controllers\Api\Sections\ManualPayments\ExtractIntegerFromKeys\ExtractIntegerFromKeysTrait;
    use \App\Http\Controllers\Api\Sections\ManualPayments\ExtractManualPaymentMetadataPayload\ExtractManualPaymentMetadataPayloadTrait;
    use \App\Http\Controllers\Api\Sections\ManualPayments\GenerateManualPaymentSignedUrl\GenerateManualPaymentSignedUrlTrait;
    use \App\Http\Controllers\Api\Sections\ManualPayments\GetDefaultCurrencyCode\GetDefaultCurrencyCodeTrait;
    use \App\Http\Controllers\Api\Sections\ManualPayments\GetManualBanks\GetManualBanksTrait;
    use \App\Http\Controllers\Api\Sections\ManualPayments\GetManualPaymentRequests\GetManualPaymentRequestsTrait;
    use \App\Http\Controllers\Api\Sections\ManualPayments\ManualPaymentGatewayAliasMap\ManualPaymentGatewayAliasMapTrait;
    use \App\Http\Controllers\Api\Sections\ManualPayments\MergeManualPaymentAttachmentCollections\MergeManualPaymentAttachmentCollectionsTrait;
    use \App\Http\Controllers\Api\Sections\ManualPayments\MergeManualPaymentMeta\MergeManualPaymentMetaTrait;
    use \App\Http\Controllers\Api\Sections\ManualPayments\NormalizeDepartmentValue\NormalizeDepartmentValueTrait;
    use \App\Http\Controllers\Api\Sections\ManualPayments\NormalizeManualPaymentDateValue\NormalizeManualPaymentDateValueTrait;
    use \App\Http\Controllers\Api\Sections\ManualPayments\NormalizeManualPaymentGateway\NormalizeManualPaymentGatewayTrait;
    use \App\Http\Controllers\Api\Sections\ManualPayments\NormalizeManualPaymentRequestFilters\NormalizeManualPaymentRequestFiltersTrait;
    use \App\Http\Controllers\Api\Sections\ManualPayments\NormalizeManualPaymentRequestStatus\NormalizeManualPaymentRequestStatusTrait;
    use \App\Http\Controllers\Api\Sections\ManualPayments\NormalizeManualPaymentString\NormalizeManualPaymentStringTrait;
    use \App\Http\Controllers\Api\Sections\ManualPayments\ResolveManualPayableType\ResolveManualPayableTypeTrait;
    use \App\Http\Controllers\Api\Sections\ManualPayments\ResolveManualPaymentDepartment\ResolveManualPaymentDepartmentTrait;
    use \App\Http\Controllers\Api\Sections\ManualPayments\SanitizeManualPaymentAttachment\SanitizeManualPaymentAttachmentTrait;
    use \App\Http\Controllers\Api\Sections\ManualPayments\SanitizeManualPaymentMetadataArray\SanitizeManualPaymentMetadataArrayTrait;
    use \App\Http\Controllers\Api\Sections\ManualPayments\ShowManualPaymentRequest\ShowManualPaymentRequestTrait;
    use \App\Http\Controllers\Api\Sections\ManualPayments\StoreManualPaymentRequest\StoreManualPaymentRequestTrait;
    use \App\Http\Controllers\Api\Sections\ManualPayments\SummarizeManualPaymentRequests\SummarizeManualPaymentRequestsTrait;

    use \App\Http\Controllers\Api\Sections\Chat\BuildParticipantsPayload\BuildParticipantsPayloadTrait;
    use \App\Http\Controllers\Api\Sections\Chat\BuildSupportTicketDescription\BuildSupportTicketDescriptionTrait;
    use \App\Http\Controllers\Api\Sections\Chat\ChatConversationsSupportsColumn\ChatConversationsSupportsColumnTrait;
    use \App\Http\Controllers\Api\Sections\Chat\CreateItemOffer\CreateItemOfferTrait;
    use \App\Http\Controllers\Api\Sections\Chat\EnrichOfferWithChatData\EnrichOfferWithChatDataTrait;
    use \App\Http\Controllers\Api\Sections\Chat\ExtractMessageIdsFromRequest\ExtractMessageIdsFromRequestTrait;
    use \App\Http\Controllers\Api\Sections\Chat\FormatMessageUpdateResponse\FormatMessageUpdateResponseTrait;
    use \App\Http\Controllers\Api\Sections\Chat\GetChatList\GetChatListTrait;
    use \App\Http\Controllers\Api\Sections\Chat\GetChatMessages\GetChatMessagesTrait;
    use \App\Http\Controllers\Api\Sections\Chat\HandleSupportEscalation\HandleSupportEscalationTrait;
    use \App\Http\Controllers\Api\Sections\Chat\HydrateLegacyChatConversation\HydrateLegacyChatConversationTrait;
    use \App\Http\Controllers\Api\Sections\Chat\MarkMessageDelivered\MarkMessageDeliveredTrait;
    use \App\Http\Controllers\Api\Sections\Chat\MarkMessageRead\MarkMessageReadTrait;
    use \App\Http\Controllers\Api\Sections\Chat\NotifySupportAgent\NotifySupportAgentTrait;
    use \App\Http\Controllers\Api\Sections\Chat\OpenSupportTicket\OpenSupportTicketTrait;
    use \App\Http\Controllers\Api\Sections\Chat\ResolveAuthorizedMessages\ResolveAuthorizedMessagesTrait;
    use \App\Http\Controllers\Api\Sections\Chat\ResolveConversationAssignedAgent\ResolveConversationAssignedAgentTrait;
    use \App\Http\Controllers\Api\Sections\Chat\ResolveLegacyLastMessageTimes\ResolveLegacyLastMessageTimesTrait;
    use \App\Http\Controllers\Api\Sections\Chat\SendMessage\SendMessageTrait;
    use \App\Http\Controllers\Api\Sections\Chat\SyncConversationDepartmentAndAssignment\SyncConversationDepartmentAndAssignmentTrait;
    use \App\Http\Controllers\Api\Sections\Chat\UpdatePresenceStatus\UpdatePresenceStatusTrait;
    use \App\Http\Controllers\Api\Sections\Chat\UpdateTypingStatus\UpdateTypingStatusTrait;

    use \App\Http\Controllers\Api\Sections\Delegates\GetAllowedSections\GetAllowedSectionsTrait;

    use \App\Http\Controllers\Api\Sections\Blocking\BlockUser\BlockUserTrait;
    use \App\Http\Controllers\Api\Sections\Blocking\GetBlockedUsers\GetBlockedUsersTrait;
    use \App\Http\Controllers\Api\Sections\Blocking\UnblockUser\UnblockUserTrait;

    use \App\Http\Controllers\Api\Sections\Content\GetAllBlogTags\GetAllBlogTagsTrait;
    use \App\Http\Controllers\Api\Sections\Content\GetBlog\GetBlogTrait;
    use \App\Http\Controllers\Api\Sections\Content\GetFaqs\GetFaqsTrait;
    use \App\Http\Controllers\Api\Sections\Content\GetTips\GetTipsTrait;

    use \App\Http\Controllers\Api\Sections\Locations\ApplyBoundingBoxFilter\ApplyBoundingBoxFilterTrait;
    use \App\Http\Controllers\Api\Sections\Locations\GetAreas\GetAreasTrait;
    use \App\Http\Controllers\Api\Sections\Locations\GetCities\GetCitiesTrait;
    use \App\Http\Controllers\Api\Sections\Locations\GetCountries\GetCountriesTrait;
    use \App\Http\Controllers\Api\Sections\Locations\GetStates\GetStatesTrait;
    use \App\Http\Controllers\Api\Sections\Locations\RequestHasBoundingBox\RequestHasBoundingBoxTrait;

    use \App\Http\Controllers\Api\Sections\Reviews\AddItemReview\AddItemReviewTrait;
    use \App\Http\Controllers\Api\Sections\Reviews\AddReviewReport\AddReviewReportTrait;
    use \App\Http\Controllers\Api\Sections\Reviews\AddServiceReview\AddServiceReviewTrait;
    use \App\Http\Controllers\Api\Sections\Reviews\AddServiceReviewReport\AddServiceReviewReportTrait;
    use \App\Http\Controllers\Api\Sections\Reviews\GetMyReview\GetMyReviewTrait;
    use \App\Http\Controllers\Api\Sections\Reviews\GetMyServiceReviews\GetMyServiceReviewsTrait;
    use \App\Http\Controllers\Api\Sections\Reviews\GetServiceReviews\GetServiceReviewsTrait;
    use \App\Http\Controllers\Api\Sections\Reviews\NotifyServiceOwnerAboutReview\NotifyServiceOwnerAboutReviewTrait;
    use \App\Http\Controllers\Api\Sections\Reviews\UserCanReviewService\UserCanReviewServiceTrait;

    use \App\Http\Controllers\Api\Sections\Verification\BuildVerificationBenefits\BuildVerificationBenefitsTrait;
    use \App\Http\Controllers\Api\Sections\Verification\GetVerificationFields\GetVerificationFieldsTrait;
    use \App\Http\Controllers\Api\Sections\Verification\GetVerificationMetadata\GetVerificationMetadataTrait;
    use \App\Http\Controllers\Api\Sections\Verification\GetVerificationRequest\GetVerificationRequestTrait;
    use \App\Http\Controllers\Api\Sections\Verification\HasVerificationInput\HasVerificationInputTrait;
    use \App\Http\Controllers\Api\Sections\Verification\MapAccountTypeIntToSlug\MapAccountTypeIntToSlugTrait;
    use \App\Http\Controllers\Api\Sections\Verification\NormalizeAccountTypeSlug\NormalizeAccountTypeSlugTrait;
    use \App\Http\Controllers\Api\Sections\Verification\NormalizeVerificationFieldValue\NormalizeVerificationFieldValueTrait;
    use \App\Http\Controllers\Api\Sections\Verification\ResolveAccountTypeSlug\ResolveAccountTypeSlugTrait;
    use \App\Http\Controllers\Api\Sections\Verification\SendVerificationRequest\SendVerificationRequestTrait;
    use \App\Http\Controllers\Api\Sections\Verification\SerializeVerificationField\SerializeVerificationFieldTrait;
    use \App\Http\Controllers\Api\Sections\Verification\ValidateRequiredVerificationFields\ValidateRequiredVerificationFieldsTrait;

    use \App\Http\Controllers\Api\Sections\Services\BuildPublicStorageUrl\BuildPublicStorageUrlTrait;
    use \App\Http\Controllers\Api\Sections\Services\DeleteOwnedService\DeleteOwnedServiceTrait;
    use \App\Http\Controllers\Api\Sections\Services\DeleteServiceMedia\DeleteServiceMediaTrait;
    use \App\Http\Controllers\Api\Sections\Services\FormatServiceFieldValueForApi\FormatServiceFieldValueForApiTrait;
    use \App\Http\Controllers\Api\Sections\Services\GetManagedService\GetManagedServiceTrait;
    use \App\Http\Controllers\Api\Sections\Services\GetOwnedServices\GetOwnedServicesTrait;
    use \App\Http\Controllers\Api\Sections\Services\GetServices\GetServicesTrait;
    use \App\Http\Controllers\Api\Sections\Services\MapService\MapServiceTrait;
    use \App\Http\Controllers\Api\Sections\Services\NormalizeServiceFieldIconPath\NormalizeServiceFieldIconPathTrait;
    use \App\Http\Controllers\Api\Sections\Services\TransformServiceFieldsSchema\TransformServiceFieldsSchemaTrait;
    use \App\Http\Controllers\Api\Sections\Services\UpdateOwnedService\UpdateOwnedServiceTrait;

    use \App\Http\Controllers\Api\Sections\Currency\GetCurrencyRates\GetCurrencyRatesTrait;
    use \App\Http\Controllers\Api\Sections\Currency\UpdateCurrencyRate\UpdateCurrencyRateTrait;

    use \App\Http\Controllers\Api\Sections\Referrals\BuildReferralLocationPayload\BuildReferralLocationPayloadTrait;
    use \App\Http\Controllers\Api\Sections\Referrals\BuildReferralRequestMeta\BuildReferralRequestMetaTrait;
    use \App\Http\Controllers\Api\Sections\Referrals\FallbackSellerName\FallbackSellerNameTrait;
    use \App\Http\Controllers\Api\Sections\Referrals\FormatReferralAttempt\FormatReferralAttemptTrait;
    use \App\Http\Controllers\Api\Sections\Referrals\GetChallenges\GetChallengesTrait;
    use \App\Http\Controllers\Api\Sections\Referrals\GetUserReferralPoints\GetUserReferralPointsTrait;
    use \App\Http\Controllers\Api\Sections\Referrals\HandleReferralCode\HandleReferralCodeTrait;
    use \App\Http\Controllers\Api\Sections\Referrals\SendReferralStatusNotification\SendReferralStatusNotificationTrait;

    use \App\Http\Controllers\Api\Sections\Shared\ResolvePerPage\ResolvePerPageTrait;



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
        'ريال يمني' => 'YER',
        'الريال اليمني' => 'YER',
        'ر.ي' => 'YER',
        'ر ي' => 'YER',
        'sar' => 'SAR',
        'ريال سعودي' => 'SAR',
        'الريال السعودي' => 'SAR',
        'ر.س' => 'SAR',
        'ر س' => 'SAR',
        'omr' => 'OMR',
        'ريال عماني' => 'OMR',
        'الريال العماني' => 'OMR',
        'ر.ع' => 'OMR',
        'ر ع' => 'OMR',
        'aed' => 'AED',
        'درهم إماراتي' => 'AED',
        'الدرهم الإماراتي' => 'AED',
        'درهم اماراتي' => 'AED',
        'الدرهم الاماراتي' => 'AED',
        'د.إ' => 'AED',
        'د إ' => 'AED',
        'kwd' => 'KWD',
        'دينار كويتي' => 'KWD',
        'الدينار الكويتي' => 'KWD',
        'د.ك' => 'KWD',
        'د ك' => 'KWD',
        'bhd' => 'BHD',
        'دينار بحريني' => 'BHD',
        'الدينار البحريني' => 'BHD',
        'د.ب' => 'BHD',
        'د ب' => 'BHD',
        'egp' => 'EGP',
        'جنيه مصري' => 'EGP',
        'الجنيه المصري' => 'EGP',
        'ج.م' => 'EGP',
        'ج م' => 'EGP',
        'usd' => 'USD',
        'دولار' => 'USD',
        'دولار أمريكي' => 'USD',
        'الدولار الأمريكي' => 'USD',
        'دولار امريكي' => 'USD',
        'الدولار الامريكي' => 'USD',
        '$' => 'USD',
        'eur' => 'EUR',
        'يورو' => 'EUR',
        'اليورو' => 'EUR',
        'gbp' => 'GBP',
        'جنيه إسترليني' => 'GBP',
        'الجنيه الإسترليني' => 'GBP',
        'جنيه استرليني' => 'GBP',
        'الجنيه الاسترليني' => 'GBP',
        'try' => 'TRY',
        'ليرة تركية' => 'TRY',
        'الليرة التركية' => 'TRY',
    ];





    /**
     * @param array<int, array<string, mixed>> $fields
     * @return array<int, array{key: string, label: string, required: bool, rules: array<int, string>}>
     */


    /**
     * @return array<string, mixed>|null
     */


    private string $uploadFolder;
    private array $departmentCategoryMap = [];
    private ?array $geoDisabledCategoryCache = null;
    private ?array $productLinkRequiredCategoryCache = null;
    private ?array $productLinkRequiredSectionCache = null;
    private ?array $interfaceSectionCategoryCache = null;





















    /**
     * @return array<int, string>
     */

    /**
     * @return array<int, string>
     */

    /**
     * @param array<int, string> $keys
     * @param array<string, mixed> $seed
     * @return array<string, mixed>
     */

    /**
     * @param array<string, mixed> $settings
     * @return array<int, array{key: string, label: string, icon: mixed, url: string, department: mixed}>
     */










    /**
     * @return array<string, mixed>
     */









     








    /**
     * @param  AbstractPaginator|Collection  $result
     */




    






























    


































    
    






















    /**
     * @return array{available: array<int, array<string, string>>, applied?: string, default?: string}
     */




































































    /**
     * Get services based on category and is_main flag
     *
     * @param Request $request
     * @return void
     */






















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
    // public function getSpecificCategories(Request $request) {
    //     try {
    //         if (!$request->has('category_id') && !$request->has('categories')) {
    //             // Default categories from the request
    //             $defaultCategoryIds = [2, 8, 174, 175, 176, 114, 181, 180, 177];
    //             $query->whereIn('category_id', $defaultCategoryIds);
    //         } elseif ($request->has('categories')) {
    //             // If categories array is provided
    //             $categoryIds = explode(',', $request->categories);
    //             $query->whereIn('category_id', $categoryIds);
    //         }

    //         $services = $query->with('category')->paginate($request->per_page ?? 15);

    //         ResponseService::successResponse("Services Fetched Successfully", $services);
    //     } catch (Throwable $th) {
    //         ResponseService::logErrorResponse($th, "API Controller -> getServices");
    //         ResponseService::errorResponse();
    //     }
    // }
    
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
     * @return array<string, mixed>
     */

    /**
     * Resolve a non-null display name for seller accounts.
     */





    


    

    /**
     * Get user profile statistics
     */




















































    






























    /**
     * Resolve the reporting department for a given category id.
     */








    /**
     * Filter the item select columns to include only those that are available on the table.
     */
  

    /**
     * Generate a fallback email address for phone-based signups.
     */



    /**
     * Retrieve and cache the available columns on the items table.
     */

    /**
     * Expand the provided category ids to include all their descendant ids.
     *
     * @param array<int> $categoryIds
     * @return array<int>
     */

    /**
     * Return the given category id plus all its descendants.
     */
}

