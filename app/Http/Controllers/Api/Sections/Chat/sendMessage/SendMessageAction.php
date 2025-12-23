<?php

namespace App\Http\Controllers\Api\Sections\Chat\sendMessage;

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


trait SendMessageAction
{
    public function sendMessage(Request $request) {
            $validator = Validator::make($request->all(), [
                'item_offer_id' => 'required|integer',
                'message'       => (!$request->file('file') && !$request->file('audio')) ? "required" : "nullable",
                'file'          => 'nullable|mimes:jpg,jpeg,png|max:4096',
                'audio'         => 'nullable|mimetypes:audio/mpeg,video/mp4,audio/x-wav,text/plain|max:4096',
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            try {
                DB::beginTransaction();
                $user = Auth::user();
                //List of users that Auth user has blocked
                $authUserBlockList = BlockUser::where('user_id', $user->id)->get();

                //List of Other users that have blocked the Auth user
                $otherUserBlockList = BlockUser::where('blocked_user_id', $user->id)->get();

                $itemOffer = ItemOffer::with('item')->findOrFail($request->item_offer_id);
                if ($itemOffer->seller_id == $user->id) {
                    //If Auth user is seller then check if buyer has blocked the user
                    $blockStatus = $authUserBlockList->filter(function ($data) use ($itemOffer) {
                        return $data->user_id == $itemOffer->seller_id && $data->blocked_user_id == $itemOffer->buyer_id;
                    });
                    if (count($blockStatus) !== 0) {
                        ResponseService::errorResponse("You Cannot send message because You have blocked this user");
                    }

                    $blockStatus = $otherUserBlockList->filter(function ($data) use ($itemOffer) {
                        return $data->user_id == $itemOffer->buyer_id && $data->blocked_user_id == $itemOffer->seller_id;
                    });
                    if (count($blockStatus) !== 0) {
                        ResponseService::errorResponse("You Cannot send message because other user has blocked you.");
                    }
                } else {
                    //If Auth user is seller then check if buyer has blocked the user
                    $blockStatus = $authUserBlockList->filter(function ($data) use ($itemOffer) {
                        return $data->user_id == $itemOffer->buyer_id && $data->blocked_user_id == $itemOffer->seller_id;
                    });
                    if (count($blockStatus) !== 0) {
                        ResponseService::errorResponse("You Cannot send message because You have blocked this user");
                    }

                    $blockStatus = $otherUserBlockList->filter(function ($data) use ($itemOffer) {
                        return $data->user_id == $itemOffer->seller_id && $data->blocked_user_id == $itemOffer->buyer_id;
                    });
                    if (count($blockStatus) !== 0) {
                        ResponseService::errorResponse("You Cannot send message because other user has blocked you.");
                    }
                }

                $department = $this->resolveSectionByCategoryId($itemOffer->item?->category_id);
                $delegates = !empty($department)
                    ? $this->delegateAuthorizationService->getDelegatesForSection($department)
                    : [];
                $assignedAgentId = $this->resolveConversationAssignedAgent($itemOffer, $delegates);


                $conversationAttributes = [];

                if ($department !== null && $this->chatConversationsSupportsColumn('department')) {
                    $conversationAttributes['department'] = $department;
                }

                if ($assignedAgentId !== null && $this->chatConversationsSupportsColumn('assigned_to')) {
                    $conversationAttributes['assigned_to'] = $assignedAgentId;
                }


                $conversation = Chat::firstOrCreate(
                    [
                        'item_offer_id' => $itemOffer->id,
                    ],
                    $conversationAttributes

                );



                $conversationWasJustCreated = $conversation->wasRecentlyCreated;
                $assignmentWasAutoUpdated = false;

                if (!$conversationWasJustCreated) {
                    $assignmentWasAutoUpdated = $this->syncConversationDepartmentAndAssignment(
                        $conversation,
                        $department,
                        $assignedAgentId
                    );
                }


                $conversation->participants()->syncWithoutDetaching(array_filter([
                    $itemOffer->seller_id,
                    $itemOffer->buyer_id,
                ]));

                            $now = Carbon::now();

                $conversation->participants()->updateExistingPivot($user->id, [
                    'is_online' => true,
                    'last_seen_at' => $now,
                    'is_typing' => false,
                    'last_typing_at' => $now,
                    'updated_at' => $now,
                ]);


                $filePath = $request->hasFile('file') ? FileService::compressAndUpload($request->file('file'), 'chat') : null;
                $audioPath = $request->hasFile('audio') ? FileService::compressAndUpload($request->file('audio'), 'chat') : null;

                $chatMessage = $conversation->messages()->create([
                    'sender_id' => Auth::id(),
                    'message'   => $request->message,
                    'file'      => $filePath,
                    'audio'     => $audioPath,
                    'status'    => ChatMessage::STATUS_SENT,

                ]);

                $conversation->touch();

                $chatMessage->load('sender');


                if ($conversationWasJustCreated || $assignmentWasAutoUpdated) {
                    $this->handleSupportEscalation(
                        $conversation,
                        $chatMessage,
                        $conversation->department ?? $department,
                        $user
                    );
                }




                try {
                    broadcast(new UserTyping($conversation, $user, false, $now))->toOthers();
                } catch (Throwable $broadcastException) {
                    \Log::warning('Broadcast typing indicator failed', [
                        'conversation_id' => $conversation->id,
                        'user_id' => $user->id,
                        'error' => $broadcastException->getMessage(),
                    ]);
                }

                try {
                    broadcast(new MessageSent($conversation, $chatMessage))->toOthers();
                } catch (Throwable $broadcastException) {
                    \Log::warning('Broadcast chat message failed', [
                        'conversation_id' => $conversation->id,
                        'message_id' => $chatMessage->id,
                        'error' => $broadcastException->getMessage(),
                    ]);
                }



                if ($itemOffer->seller_id == $user->id) {
                    $receiver_id = $itemOffer->buyer_id;
                    $userType = "Seller";
                } else {
                    $receiver_id = $itemOffer->seller_id;
                    $userType = "Buyer";
                }

                $notificationPayload = $chatMessage->toArray();
                $messageType = $notificationPayload['message_type'] ?? null;
                $notificationPayload['item_offer_id'] = $conversation->item_offer_id;
                $notificationPayload['conversation_id'] = $conversation->id;
                $messagePreview = $request->message ?? $chatMessage->message ?? '';




                $fcmMsg = [
                    ...$notificationPayload,
                    'user_id'             => $user->id,
                    'user_name'           => $user->name,
                    'user_profile'        => $user->profile,
                    'user_type'           => $userType,
                    'item_id'             => $itemOffer->item->id,
                    'item_name'           => $itemOffer->item->name,
                    'item_image'          => $itemOffer->item->image,
                    'item_price'          => $itemOffer->item->price,
                    'item_offer_id'       => $itemOffer->id,
                    'item_offer_amount'   => $itemOffer->amount,
                    'notification_type'   => 'chat',
                    'type'                => 'chat',
                    'chat_message_type'   => $notificationPayload['message_type'] ?? null,
                    'message_preview'     => $messagePreview,
                ];

                $receiverFCMTokens = UserFcmToken::where('user_id', $receiver_id)
                    ->pluck('fcm_token')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if (empty($receiverFCMTokens)) {
                    \Log::info('ApiController: No FCM tokens found for chat receiver.', [
                        'conversation_id' => $conversation->id,
                        'receiver_id' => $receiver_id,
                    ]);

                    $notification = [
                        'error' => false,
                        'message' => 'Receiver has no notification tokens.',
                        'data' => [],
                    ];
                } else {
                    $notification = NotificationService::sendFcmNotification(
                        $receiverFCMTokens,
                        'Message',
                        $request->message,
                        'chat',
                        $fcmMsg
                    );


                }



                if (is_array($notification) && ($notification['error'] ?? false)) {
                    \Log::warning('ApiController: Failed to send chat notification', $notification);
                }

                DB::commit();
                ResponseService::successResponse("Message Fetched Successfully", $chatMessage, ['debug' => $notification]);

            } catch (Throwable $th) {
                DB::rollBack();
                ResponseService::logErrorResponse($th, "API Controller -> sendMessage");
                ResponseService::errorResponse();
            }
        }
}
