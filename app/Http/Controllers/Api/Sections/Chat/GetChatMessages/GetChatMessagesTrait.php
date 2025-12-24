<?php

namespace App\Http\Controllers\Api\Sections\Chat\GetChatMessages;

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

trait GetChatMessagesTrait
{
    public function getChatMessages(Request $request) {
        $validator = Validator::make($request->all(), [
            'item_offer_id' => 'sometimes|integer|exists:item_offers,id',
            'conversation_id' => 'sometimes|integer|exists:chat_conversations,id',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

                
        if (!$request->filled('item_offer_id') && !$request->filled('conversation_id')) {
            ResponseService::validationError('item_offer_id or conversation_id is required.');
        }
        try {

            $user = Auth::user();
            $conversation = null;
            $itemOffer = null;
        
            if ($request->filled('conversation_id')) {
                $conversation = Chat::with('itemOffer')->findOrFail($request->conversation_id);
        
                if (!$conversation->participants()->where('users.id', $user->id)->exists()) {
                    ResponseService::errorResponse('You are not allowed to view this conversation', null, 403);
                }
        
                $itemOffer = $conversation->itemOffer;
                if ($itemOffer && $itemOffer->seller_id !== $user->id && $itemOffer->buyer_id !== $user->id) {
                    ResponseService::errorResponse('You are not allowed to view this conversation', null, 403);
                }
            }
        
            if (!$itemOffer && $request->filled('item_offer_id')) {
                $itemOffer = ItemOffer::owner()->findOrFail($request->item_offer_id);
            }
        
            if ($conversation && $itemOffer && $conversation->item_offer_id !== $itemOffer->id) {
                ResponseService::errorResponse('Conversation does not belong to the supplied item offer.');
            }
        
            if (!$conversation && $itemOffer) {
                $conversation = Chat::where('item_offer_id', $itemOffer->id)->first();
            }
        
            $perPage = (int) $request->input('per_page', 15);


            $chatMessages = null;

            if ($conversation) {
                $chatMessages = ChatMessage::with(['sender:id,name,profile', 'conversation:id,item_offer_id'])
                    ->where('conversation_id', $conversation->id)
                    
                    ->orderBy('created_at', 'DESC')
                    ->paginate($perPage);
            }

            if (!$conversation || ($chatMessages && $chatMessages->total() === 0)) {
                if ($itemOffer) {
                    $conversation = $this->hydrateLegacyChatConversation($itemOffer, $conversation);

                    if ($conversation) {
                        $chatMessages = ChatMessage::with(['sender:id,name,profile', 'conversation:id,item_offer_id'])
                            ->where('conversation_id', $conversation->id)
                            ->orderBy('created_at', 'DESC')
                            ->paginate($perPage);
                    }
                }
            }

            if (!$conversation) {


                $empty = ChatMessage::whereRaw('1 = 0')->paginate($perPage);
                ResponseService::successResponse('Messages Fetched Successfully', $empty);
                return;
            }

            if (!$chatMessages) {
                $chatMessages = ChatMessage::whereRaw('1 = 0')->paginate($perPage);
            }

            ResponseService::successResponse('Messages Fetched Successfully', $chatMessages);
        
        
        
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getChatMessages');
            ResponseService::errorResponse();
        }
    }
}