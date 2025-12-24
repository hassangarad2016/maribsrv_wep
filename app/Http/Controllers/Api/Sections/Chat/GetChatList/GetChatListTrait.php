<?php

namespace App\Http\Controllers\Api\Sections\Chat\GetChatList;

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

trait GetChatListTrait
{
    public function getChatList(Request $request) {
        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|in:seller,buyer',
            'conversation_id' => 'sometimes|integer|exists:chat_conversations,id',
            'item_offer_id' => 'sometimes|integer|exists:item_offers,id',
            'page' => 'sometimes|integer|min:1',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        if (!$request->filled('type') && !$request->filled('conversation_id') && !$request->filled('item_offer_id')) {
            ResponseService::validationError('type is required when conversation_id or item_offer_id is not provided.');
        }

        try {



            $user = Auth::user();
            $authUserBlockList = BlockUser::where('user_id', $user->id)->pluck('blocked_user_id');
            $otherUserBlockList = BlockUser::where('blocked_user_id', $user->id)->pluck('user_id');
    
            $baseRelations = [


                'seller' => function ($query) {
                    $query->withTrashed()->select('id', 'name', 'profile');
                },
                'buyer' => function ($query) {
                    $query->withTrashed()->select('id', 'name', 'profile');
                },
                'item:id,name,description,price,image,status,deleted_at,sold_to',
                'item.review' => function ($q) use ($user) {
                    $q->where('buyer_id', $user->id);
                },
                'chat' => function ($query) use ($user) {
                    $query->latest('updated_at')
                        ->select('id', 'item_offer_id', 'updated_at')
                        ->with([
                            'participants' => function ($participantQuery) {
                                $participantQuery->withTrashed()->select('users.id', 'users.name', 'users.profile');
                            },
                            'latestMessage' => function ($messageQuery) {
                                $messageQuery->with([
                                    'sender:id,name,profile',
                                    'conversation:id,item_offer_id',
                                ]);
                            },
                        ])
                        ->withCount([
                            'messages as unread_messages_count' => function ($messageQuery) use ($user) {
                                $messageQuery->whereNull('read_at')
                                    ->where(function ($subQuery) use ($user) {
                                        $subQuery->whereNull('sender_id')
                                            ->orWhere('sender_id', '!=', $user->id);
                                    });
                            },
                        ]);
                },
            ];
    
            if ($request->filled('conversation_id') || $request->filled('item_offer_id')) {
                $offerRelations = $baseRelations;
                unset($offerRelations['chat']);
    
                if ($request->filled('conversation_id')) {
                    $conversation = Chat::with(['itemOffer' => function ($query) use ($offerRelations) {
                        $query->with($offerRelations);
                    }])->findOrFail($request->conversation_id);
    
                    if (!$conversation->participants()->where('users.id', $user->id)->exists()) {
                        ResponseService::errorResponse('You are not allowed to view this conversation', null, 403);
                    }
                    $itemOffer = $conversation->itemOffer;
                    if (!$itemOffer) {
                        ResponseService::errorResponse('Conversation is missing item offer reference');
                    }
                    $itemOffer->loadMissing($offerRelations);
    
                    $legacyTimes = $this->resolveLegacyLastMessageTimes(collect([$itemOffer->id]));
                    $type = $itemOffer->seller_id === $user->id ? 'seller' : 'buyer';
                    $payload = $this->enrichOfferWithChatData(
                        $itemOffer,
                        $user,
                        $authUserBlockList,
                        $otherUserBlockList,
                        $legacyTimes,
                        $type,
                        $conversation
                    );
    
                    ResponseService::successResponse('Chat conversation fetched successfully', [
                        'conversation' => $payload->toArray(),
                    ]);
                    return;

                }

                $itemOffer = ItemOffer::with($baseRelations)
                    ->owner()
                    ->findOrFail($request->item_offer_id);
    
                $legacyTimes = $this->resolveLegacyLastMessageTimes(collect([$itemOffer->id]));
                $type = $request->input('type');
                if (!$type) {
                    $type = $itemOffer->seller_id === $user->id ? 'seller' : 'buyer';
                }

                $payload = $this->enrichOfferWithChatData(
                    $itemOffer,
                    $user,
                    $authUserBlockList,
                    $otherUserBlockList,
                    $legacyTimes,
                    $type
                );
    
                ResponseService::successResponse('Chat conversation fetched successfully', [
                    'conversation' => $payload->toArray(),
                ]);
                return;
            }
    
            $itemOffer = ItemOffer::with($baseRelations)
                ->orderBy('id', 'DESC');
    
            if ($request->type === 'seller') {
                $itemOffer->where('seller_id', $user->id);
            } elseif ($request->type === 'buyer') {
                $itemOffer->where('buyer_id', $user->id);
            }
    
            $itemOffer = $itemOffer->paginate();
    
            $offerIds = $itemOffer->getCollection()->pluck('id')->filter()->values();
            $legacyLastMessageTimes = $this->resolveLegacyLastMessageTimes($offerIds);
    
            $itemOffer->getCollection()->transform(function (ItemOffer $offer) use (
                $user,
                $authUserBlockList,
                $otherUserBlockList,
                $legacyLastMessageTimes,
                $request
            ) {
                return $this->enrichOfferWithChatData(
                    $offer,
                    $user,
                    $authUserBlockList,
                    $otherUserBlockList,
                    $legacyLastMessageTimes,
                    $request->type
                );


            });

            ResponseService::successResponse('Chat List Fetched Successfully', $itemOffer);


        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getChatList');
            ResponseService::errorResponse();
        }
    }
}