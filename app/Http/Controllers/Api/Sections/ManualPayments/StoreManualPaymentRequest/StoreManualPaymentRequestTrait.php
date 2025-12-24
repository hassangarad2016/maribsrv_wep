<?php

namespace App\Http\Controllers\Api\Sections\ManualPayments\StoreManualPaymentRequest;

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

trait StoreManualPaymentRequestTrait
{
    public function storeManualPaymentRequest(Request $request) {
        if ($request->filled('bank_id') && !$request->filled('manual_bank_id')) {
            // â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´â”¬ط¨â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط±â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–‘â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچ bank_id â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط±â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬طھâ•ھطھâ”¬â•—â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط¨ â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط¨â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´â”¬ط¨ â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–“â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ”œطھط¸أ©ط´ط¸آ€آ£â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–‘â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط°â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط±â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–“ â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھطھâ”¬ط«â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط¨â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط°â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچ â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´â”¬ط¨â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھطھâ”¬ط«â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط°â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬طھâ•ھطھâ”¬â•— â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–“â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â”œطھط¸أ©ط´â”Œظ‘â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط¨â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط°â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–“â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط¯ â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬طµâ•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط³ manual_bank_id â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط±â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–‘â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچ â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬آ»â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬طھâ”œطھط¸أ©ط´ط¸آ€آچâ•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â–“â•ھط«â”¬طھâ”œطھط¸أ©ط´â”¬طھâ•ھâ•£â”¬â•›â•ھâ•–â”¬â•–â•ھطھâ”¬â•،â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط±â•ھط«â”¬طھâ”œطھط¸أ©ط´ط¸آ€أ®â•ھâ•£â”¬ط±â•ھâ•–â”¬ط«â•ھطھâ”¬ط±.
            $request->merge(['manual_bank_id' => $request->input('bank_id')]);
        }



        $paymentMethod = $request->input('payment_method', 'manual_bank');

        $validator = Validator::make($request->all(), [
            'payment_method' => 'nullable|in:manual_bank,east_yemen_bank,wallet',
            'manual_bank_id' => 'required_if:payment_method,manual_bank|nullable|exists:manual_banks,id',
            
            'amount'         => 'required|numeric|min:0.01',
            'reference'      => 'nullable|string|max:255',
            'user_note'      => 'nullable|string',
            'receipt'        => 'required_if:payment_method,manual_bank|nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'payable_type'   => 'nullable|string',
            'payable_id'     => 'nullable|integer',
            'currency'       => 'nullable|string|max:8',
            'east_yemen_bank' => 'required_if:payment_method,east_yemen_bank|array',
            'east_yemen_bank.voucher_number' => 'required_if:payment_method,east_yemen_bank|string|max:255',
            'east_yemen_bank.payment_status' => 'nullable|string|max:255',
        
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        $validated = $validator->validated();


        $payableTypeInput = $request->input('payable_type');
        $payableIdInput = $request->input('payable_id');

        $resolvedPayableType = null;
        $payableId = $payableIdInput;




        $isWalletTopUp = is_string($payableTypeInput)
            && strtolower(trim($payableTypeInput)) === ManualPaymentRequest::PAYABLE_TYPE_WALLET_TOP_UP;

        if ($isWalletTopUp) {
            if ($request->filled('payable_id')) {
                ResponseService::validationError('Wallet top-up requests should not include a payable id.');
            }

            $walletAccount = WalletAccount::firstOrCreate(
                [
                    'user_id' => Auth::id(),
                ],
                [
                    'currency' => $this->getWalletCurrencyCode(),
                ]
            );
            $walletAccount = $this->ensureWalletAccountCurrency($walletAccount);

            $resolvedPayableType = ManualPaymentRequest::PAYABLE_TYPE_WALLET_TOP_UP;
            $payableId = $walletAccount->getKey();
        } elseif (!empty($payableTypeInput) || !empty($payableIdInput)) {
            if (empty($payableTypeInput) || empty($payableIdInput)) {




                ResponseService::validationError('Payable type and payable id are required together.');
            }

            $resolvedPayableType = $this->resolveManualPayableType($payableTypeInput);

            if (empty($resolvedPayableType)) {
                ResponseService::validationError('Invalid payable type supplied.');
            }

            if (!$resolvedPayableType::whereKey($payableIdInput)->exists()) {
                ResponseService::validationError('Unable to locate the selected payable record.');
            }
        }


        $manualBank = null;

        if ($paymentMethod === 'manual_bank' && $request->filled('manual_bank_id')) {
            $manualBank = ManualBank::query()->find((int) $request->input('manual_bank_id'));
        }

        $metaUpdates = $this->buildManualPaymentMetaUpdates(
            $request,
            is_string($paymentMethod) ? $paymentMethod : 'manual_bank',
            $isWalletTopUp,
            $manualBank
        );




        $requestedCurrency = $request->input('currency');
        $currency = filled($requestedCurrency)
            ? strtoupper($requestedCurrency)
            : $this->getDefaultCurrencyCode();
        $walletCurrency = $this->getWalletCurrencyCode();

        if ($isWalletTopUp || $paymentMethod === 'wallet') {
            if ($requestedCurrency !== null && strtoupper($requestedCurrency) !== $walletCurrency) {
                ResponseService::validationError(sprintf(
                    'Wallet transactions must use the %s currency.',
                    $walletCurrency
                ));
            }

            $currency = $walletCurrency;
        }

        $user = Auth::user();

        if ($paymentMethod === 'wallet' && $isWalletTopUp) {
            ResponseService::validationError('Wallet top-up requests cannot be paid using wallet balance.');
        }

        $walletIdempotencyKey = null;
        $existingTransaction = null;
        $existingManualPaymentRequest = null;


        try {
            DB::beginTransaction();



            if ($paymentMethod === 'wallet') {
                $walletIdempotencyKey = $this->buildManualPaymentWalletIdempotencyKey(
                    $user,
                    $resolvedPayableType !== ManualPaymentRequest::PAYABLE_TYPE_WALLET_TOP_UP ? $resolvedPayableType : null,
                    $resolvedPayableType !== ManualPaymentRequest::PAYABLE_TYPE_WALLET_TOP_UP ? $payableId : null,
                    (float) $request->amount,
                    $currency
                );

                $existingTransaction = $this->findWalletPaymentTransaction(
                    $user->id,
                    $resolvedPayableType !== ManualPaymentRequest::PAYABLE_TYPE_WALLET_TOP_UP ? $resolvedPayableType : null,
                    $resolvedPayableType !== ManualPaymentRequest::PAYABLE_TYPE_WALLET_TOP_UP ? $payableId : null,
                    $walletIdempotencyKey
                );

                if ($existingTransaction && $existingTransaction->manual_payment_request_id) {
                    $existingManualPaymentRequest = ManualPaymentRequest::query()
                        ->whereKey($existingTransaction->manual_payment_request_id)
                        ->lockForUpdate()
                        ->first();
                }

                if ($existingTransaction && strtolower($existingTransaction->payment_status) === 'succeed') {
                    DB::commit();

                    if ($existingManualPaymentRequest) {
                        $existingManualPaymentRequest->loadMissing('manualBank', 'payable', 'paymentTransaction');

                        ResponseService::successResponse(
                            'Transaction already processed',
                            ManualPaymentRequestResource::make($existingManualPaymentRequest)->resolve()
                        );
                    }

                    ResponseService::successResponse('Transaction already processed');
                }
            }



            $receiptPath = null;
            if ($request->hasFile('receipt')) {
                $receiptPath = $request->file('receipt')->store('manual_payments', 'public');
                
            } elseif ($existingManualPaymentRequest) {
                $receiptPath = $existingManualPaymentRequest->receipt_path;

            }

            $metaUpdates = $this->appendManualPaymentReceiptMeta($metaUpdates, $receiptPath);

            $existingMeta = $existingManualPaymentRequest?->meta;
            $metaPayload = $this->mergeManualPaymentMeta(
                is_array($existingMeta) ? $existingMeta : [],
                $metaUpdates
            );


            $department = $this->resolveManualPaymentDepartment(
                $resolvedPayableType,
                $payableId,
                $existingManualPaymentRequest
            );

            $serviceRequestId = null;
            if (is_numeric($payableId)) {
                $normalizedPayableType = is_string($resolvedPayableType)
                    ? strtolower(trim($resolvedPayableType, " \t\n\r\0\x0B\"'"))
                    : null;

                if ($normalizedPayableType !== null) {
                    $serviceAliases = [
                        strtolower(ServiceRequest::class),
                        strtolower('\\' . ServiceRequest::class),
                        'app\\models\\servicerequest',
                        'app\\servicerequest',
                        'service_request',
                        'service-request',
                    ];

                    if (in_array($normalizedPayableType, $serviceAliases, true)) {
                        $serviceRequestId = (int) $payableId;
                    }
                }
            }


            $manualPaymentAttributes = [
                'user_id'        => $user->id,

                'manual_bank_id' => $paymentMethod === 'manual_bank' ? $request->manual_bank_id : null,
                'amount'         => $request->amount,
                'currency'       => $currency,


                'reference'      => $request->reference,
                'user_note'      => $request->user_note,
                'receipt_path'   => $receiptPath,
                'status'         => ManualPaymentRequest::STATUS_PENDING,
                'payable_type'   => $resolvedPayableType,
                'payable_id'     => $payableId,
                'service_request_id' => $serviceRequestId,
                'department'     => $department,
                'meta'           => empty($metaPayload) ? null : $metaPayload,
            ];

            if ($existingManualPaymentRequest) {




                $existingManualPaymentRequest->forceFill($manualPaymentAttributes)->save();
                $manualPaymentRequest = $existingManualPaymentRequest->fresh();

                } else {


                $manualPaymentRequest = ManualPaymentRequest::create($manualPaymentAttributes);
            }

            if ($paymentMethod === 'wallet') {
                $transactionMeta = $existingTransaction?->meta ?? [];
                $transactionMeta = array_replace_recursive($transactionMeta, $metaPayload ?? []);
                data_set($transactionMeta, 'wallet.idempotency_key', $walletIdempotencyKey);

                if ($existingTransaction) {
                    $existingTransaction->forceFill([
                        'user_id' => $user->id,
                        'manual_payment_request_id' => $manualPaymentRequest->id,
                        'amount' => $manualPaymentRequest->amount,
                        'currency' => $currency,
                        'receipt_path' => $receiptPath,
                        'payment_gateway' => 'wallet',
                        'payable_type' => ($resolvedPayableType && $resolvedPayableType !== ManualPaymentRequest::PAYABLE_TYPE_WALLET_TOP_UP)
                            ? $resolvedPayableType
                            : null,
                        'payable_id' => ($resolvedPayableType && $resolvedPayableType !== ManualPaymentRequest::PAYABLE_TYPE_WALLET_TOP_UP)
                            ? $payableId
                            : null,
                        'order_id' => $walletIdempotencyKey,
                        'meta' => $transactionMeta,
                    ])->save();

                    $paymentTransaction = $existingTransaction->fresh();
                } else {
                    $paymentTransaction = PaymentTransaction::create([
                        'user_id'                   => $user->id,
                        'manual_payment_request_id' => $manualPaymentRequest->id,
                        'amount'                    => $manualPaymentRequest->amount,
                        'currency'                  => $currency,
                        'receipt_path'              => $receiptPath,
                        'payment_gateway'           => 'wallet',
                        'payment_status'            => 'pending',
                        'payable_type'              => ($resolvedPayableType && $resolvedPayableType !== ManualPaymentRequest::PAYABLE_TYPE_WALLET_TOP_UP)
                            ? $resolvedPayableType
                            : null,
                        'payable_id'                => ($resolvedPayableType && $resolvedPayableType !== ManualPaymentRequest::PAYABLE_TYPE_WALLET_TOP_UP)
                            ? $payableId
                            : null,
                        'order_id'                  => $walletIdempotencyKey,
                        'meta'                      => empty($transactionMeta) ? null : $transactionMeta,
                    ]);
                }

                $walletTransaction = $this->debitWalletTransaction(
                    $paymentTransaction->fresh(),
                    $user,
                    $walletIdempotencyKey,
                    (float) $manualPaymentRequest->amount,
                    [
                        'manual_payment_request_id' => $manualPaymentRequest->id,
                        'meta' => [
                            'context' => 'manual_payment',
                            'payable_type' => $manualPaymentRequest->payable_type,
                            'payable_id' => $manualPaymentRequest->payable_id,
                            'manual_payment_request_id' => $manualPaymentRequest->id,
                        ],
                    ]
                );

                $transactionMeta = $paymentTransaction->meta ?? [];
                data_set($transactionMeta, 'wallet.transaction_id', $walletTransaction->getKey());
                data_set($transactionMeta, 'wallet.balance_after', (float) $walletTransaction->balance_after);
                data_set($transactionMeta, 'wallet.idempotency_key', $walletTransaction->idempotency_key);

                $paymentTransaction->forceFill([
                    'meta' => $transactionMeta,
                ])->save();

                $requestMeta = array_replace_recursive($manualPaymentRequest->meta ?? [], [
                    'wallet' => [
                        'transaction_id' => $walletTransaction->getKey(),
                        'idempotency_key' => $walletTransaction->idempotency_key,
                        'balance_after' => (float) $walletTransaction->balance_after,
                    ],
                ]);

                $manualPaymentRequest->forceFill([
                    'status' => ManualPaymentRequest::STATUS_APPROVED,
                    'reviewed_at' => now(),
                    'meta' => $requestMeta,
                ])->save();

                $options = [
                    'payment_gateway' => 'wallet',
                    'manual_payment_request_id' => $manualPaymentRequest->id,
                    'wallet_transaction' => $walletTransaction,
                    'meta' => $transactionMeta,
                ];

                $shouldFulfill = !empty($manualPaymentRequest->payable_type)
                    && $manualPaymentRequest->payable_type !== ManualPaymentRequest::PAYABLE_TYPE_WALLET_TOP_UP;

                $message = 'Manual payment completed successfully';

                if ($shouldFulfill) {
                    $result = $this->paymentFulfillmentService->fulfill(
                        $paymentTransaction->fresh(),
                        $manualPaymentRequest->payable_type,
                        $manualPaymentRequest->payable_id,
                        $user->id,
                        $options
                    );

                    if ($result['error']) {
                        throw new RuntimeException($result['message']);
                    }

                    $message = $result['message'] === 'Transaction already processed'
                        ? 'Transaction already processed'
                        : 'Manual payment completed successfully';
                }

                DB::commit();

                $manualPaymentRequest->loadMissing('manualBank', 'payable', 'paymentTransaction');


                $message = $result['message'] === 'Transaction already processed'
                    ? 'Transaction already processed'
                    : 'Manual payment completed successfully';

                ResponseService::successResponse(
                    $message,
                    ManualPaymentRequestResource::make($manualPaymentRequest)->resolve()
                );
            } elseif ($paymentMethod === 'east_yemen_bank') {
                $eastYemenData = $validated['east_yemen_bank'] ?? [];
                if (!is_array($eastYemenData)) {
                    $eastYemenData = [];
                }

                $voucherNumber = Arr::get($eastYemenData, 'voucher_number');
                $paymentStatusValue = Arr::get($eastYemenData, 'payment_status');
                $recordedAt = now()->toIso8601String();

                $transactionMeta = array_replace_recursive($metaPayload ?? [], [
                    'east_yemen_bank' => array_filter([
                        'voucher_number' => $voucherNumber,
                        'payment_status' => $paymentStatusValue,
                        'recorded_at' => $recordedAt,
                    ], static fn($value) => $value !== null && $value !== ''),
                ]);

                $transactionMeta['provider'] = 'alsharq';
                $transactionMeta['channel'] = 'alsharq';


                if ($paymentStatusValue !== null && $paymentStatusValue !== '') {
                    $transactionMeta['east_yemen_bank_status'] = $paymentStatusValue;
                }

                $paymentTransaction = PaymentTransaction::create([
                    'user_id'                   => $user->id,
                    'manual_payment_request_id' => $manualPaymentRequest->id,
                    'amount'                    => $manualPaymentRequest->amount,
                    'currency'                  => $currency,
                    'receipt_path'              => $receiptPath,
                    'payment_gateway'           => 'east_yemen_bank',
                    'payment_status'            => 'succeed',
                    'payable_type'              => ($resolvedPayableType && $resolvedPayableType !== ManualPaymentRequest::PAYABLE_TYPE_WALLET_TOP_UP)
                        ? $resolvedPayableType
                        : null,
                    'payable_id'                => ($resolvedPayableType && $resolvedPayableType !== ManualPaymentRequest::PAYABLE_TYPE_WALLET_TOP_UP)
                        ? $payableId
                        : null,
                    'order_id'                  => $voucherNumber ?: null,
                    'meta'                      => empty($transactionMeta) ? null : $transactionMeta,
                ]);

                $requestMeta = array_replace_recursive($manualPaymentRequest->meta ?? [], [
                    'east_yemen_bank' => [
                        'auto_approval' => [
                            'recorded_at' => $recordedAt,
                            'payload' => array_filter([
                                'voucher_number' => $voucherNumber,
                            ], static fn($value) => $value !== null && $value !== ''),
                            'response' => array_filter([
                                'payment_status' => $paymentStatusValue,
                            ], static fn($value) => $value !== null && $value !== ''),
                        ],
                    ],
                ]);

                $manualPaymentRequest->forceFill([
                    'status' => ManualPaymentRequest::STATUS_APPROVED,
                    'reviewed_at' => now(),
                    'meta' => $requestMeta,
                ])->save();

                ManualPaymentRequestHistory::create([
                    'manual_payment_request_id' => $manualPaymentRequest->id,
                    'user_id' => $user->id,
                    'status' => ManualPaymentRequest::STATUS_APPROVED,
                    'meta' => [
                        'action' => 'east_yemen_bank_auto_approval',
                        'gateway' => 'east_yemen_bank',
                        'payload' => array_filter([
                            'voucher_number' => $voucherNumber,
                        ], static fn($value) => $value !== null && $value !== ''),
                        'response' => array_filter([
                            'payment_status' => $paymentStatusValue,
                        ], static fn($value) => $value !== null && $value !== ''),
                    ],
                ]);

                $transactionMetaForFulfillment = $paymentTransaction->meta ?? [];

                $options = [
                    'payment_gateway' => 'east_yemen_bank',
                    'manual_payment_request_id' => $manualPaymentRequest->id,
                    'meta' => $transactionMetaForFulfillment,
                ];

                $shouldFulfill = !empty($manualPaymentRequest->payable_type)
                    && $manualPaymentRequest->payable_type !== ManualPaymentRequest::PAYABLE_TYPE_WALLET_TOP_UP;

                $message = 'Manual payment completed successfully';

                if ($shouldFulfill) {
                    $result = $this->paymentFulfillmentService->fulfill(
                        $paymentTransaction->fresh(),
                        $manualPaymentRequest->payable_type,
                        $manualPaymentRequest->payable_id,
                        $user->id,
                        $options
                    );

                    if ($result['error']) {
                        throw new RuntimeException($result['message']);
                    }

                    $message = $result['message'] === 'Transaction already processed'
                        ? 'Transaction already processed'
                        : 'Manual payment completed successfully';
                }

                DB::commit();

                $freshTransaction = $paymentTransaction->fresh();
                $manualPaymentRequest->setRelation('paymentTransaction', $freshTransaction);
                $manualPaymentRequest->loadMissing('manualBank');
                if (!empty($resolvedPayableType)) {
                    $manualPaymentRequest->loadMissing('payable');
                }




                ResponseService::successResponse(
                    $message,
                    ManualPaymentRequestResource::make($manualPaymentRequest)->resolve()
                );
            }




            $paymentTransaction = PaymentTransaction::create([
                'user_id'                   => $user->id,
                'manual_payment_request_id' => $manualPaymentRequest->id,
                'amount'                    => $manualPaymentRequest->amount,
                'currency'                  => $currency,
                'receipt_path'              => $receiptPath,
                'payment_gateway'           => $paymentMethod,
                'payment_status'            => 'pending',
                'payable_type'              => ($resolvedPayableType && $resolvedPayableType !== ManualPaymentRequest::PAYABLE_TYPE_WALLET_TOP_UP)
                    ? $resolvedPayableType
                    : null,
                'payable_id'                => ($resolvedPayableType && $resolvedPayableType !== ManualPaymentRequest::PAYABLE_TYPE_WALLET_TOP_UP)
                    ? $payableId
                    : null,
                'meta'                      => empty($metaPayload) ? null : $metaPayload,

            ]);

            DB::commit();

            $manualPaymentRequest->setRelation('paymentTransaction', $paymentTransaction);
            $manualPaymentRequest->loadMissing('manualBank');
            if (!empty($resolvedPayableType)) {
                $manualPaymentRequest->loadMissing('payable');
            }

            ResponseService::successResponse(
                'Manual Payment Request Submitted',
                ManualPaymentRequestResource::make($manualPaymentRequest)->resolve()
            );

        } catch (RuntimeException $runtimeException) {
            DB::rollBack();
            ResponseService::errorResponse($runtimeException->getMessage());

        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, 'API Controller -> storeManualPaymentRequest');
            ResponseService::errorResponse();
        }
    }
}