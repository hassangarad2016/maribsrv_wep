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
        'ريال يمني' => 'YER',
        'ريال يمني.' => 'YER',
        'ر.ي' => 'YER',
        'sar' => 'SAR',
        'ريال سعودي' => 'SAR',
        'ريال سعودي.' => 'SAR',
        'ر.س' => 'SAR',
        'omr' => 'OMR',
        'ريال عماني' => 'OMR',
        'ريال عماني.' => 'OMR',
        'ر.ع' => 'OMR',
        'aed' => 'AED',
        'درهم إماراتي' => 'AED',
        'درهم اماراتي' => 'AED',
        'kwd' => 'KWD',
        'دينار كويتي' => 'KWD',
        'دينار كويتي.' => 'KWD',
        'bhd' => 'BHD',
        'دينار بحريني' => 'BHD',
        'دينار بحريني.' => 'BHD',
        'egp' => 'EGP',
        'جنيه مصري' => 'EGP',
        'جنيه مصري.' => 'EGP',
        'usd' => 'USD',
        'دولار' => 'USD',
        'دولار أمريكي' => 'USD',
        '

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



    public function getSystemSettings(Request $request) {
        try {
            $perPage = $request->integer('per_page', 15) ?? 15;

            if ($perPage <= 0) {
                $perPage = 15;
            }

            $perPage = min($perPage, 50);


            $settingsQuery = Setting::select(['name', 'value', 'type'])->orderBy('name');


            $typeFilter = $this->normalizeSettingNames($request->input('type'));
            if (!empty($typeFilter)) {
                $settingsQuery->whereIn('name', $typeFilter);
            }
            $fieldsFilter = $this->normalizeSettingNames($request->input('fields'));
            if (!empty($fieldsFilter)) {
                $settingsQuery->whereIn('name', $fieldsFilter);
            }

            $settings = $settingsQuery
                ->paginate($perPage)
                ->withQueryString()
                ->through(static function (Setting $row): array {
                    return [
                        'name'  => $row->name,
                        'value' => $row->value,
                        'type'  => $row->type,
                    ];
                });

            $settingsCollection = $settings->getCollection();



            $currentValues = $settingsCollection
                ->filter(static function ($item) {
                    if (!is_array($item)) {
                        return false;
                    }

                    $name = $item['name'] ?? null;


                    return is_string($name) && $name !== '';
                })
                ->mapWithKeys(static fn (array $item) => [
                    $item['name'] => $item['value'] ?? null,
                ])
                ->all();

            $requiredKeys = $this->socialLinkSettingKeys();
            $hydratedValues = $this->hydrateSettingValues($requiredKeys, $currentValues);

            $socialLinks = $this->buildSocialLinks($hydratedValues);




            $supportService = app(DepartmentSupportService::class);


            $extras = [
                'social_links' => $socialLinks,
                'department_support' => $supportService->allWhatsAppSupport(),
                'demo_mode' => config('app.demo_mode'),
                'languages' => CachingService::getLanguages(),
                'admin' => User::role('Super Admin')->select(['name', 'profile'])->first(),
            ];

            ResponseService::successResponse(
                "Data Fetched Successfully",
                $settings,
                [
                    'items_key' => 'settings',
                    'append_to_data' => [
                        'extras' => $extras,
                    ],
                ]
            );
            
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getSystemSettings");
            ResponseService::errorResponse();
        }
    }

    public function userSignup(Request $request) {
        try {
            \Log::info('UserSignup Request:', [
                'type' => $request->type,
                'firebase_id' => $request->firebase_id,
                'mobile' => $request->mobile ?? 'not provided',
                'name' => $request->name ?? 'not provided',
                'email' => $request->email ?? 'not provided',
                'account_type' => $request->account_type ?? 'not provided'
            ]);
            
            $validationRules = [
                'type'          => 'required|in:email,google,phone,apple',
                'firebase_id'   => 'required',
                'country_code'  => 'nullable|string',
                'flag'          => 'boolean',
                'platform_type' => 'nullable|in:android,ios'
            ];
            
            if ($request->type == 'google') {
                $validationRules['mobile'] = 'nullable'; // أ¢â€¢ع¾ط·آ´أ¢â€¢ع¾أ¢â€¢آ£أ¢â€‌ع©ط¢â€‍ أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط£آ§أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾ط·آ²أ¢â€‌ع©ط¢ظ¾ أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾ط¢آ«أ¢â€¢ع¾ط·آ²أ¢â€‌ع©ط£آ¨أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾أ¢â€“â€™أ¢â€‌ع©ط£آ¨ أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط¢â‚¬ Google
            } elseif ($request->type == 'phone') {
                $validationRules['mobile'] = 'required|unique:users,mobile';
            } elseif ($request->type == 'email') {
                $validationRules['email'] = 'required|email';
            }
            if ($request->filled('code')) {
                $validationRules['lat'] = 'required|numeric';
                $validationRules['lng'] = 'required|numeric';
                $validationRules['device_time'] = 'required';
                $validationRules['admin_area'] = 'nullable|string|max:255';
            }

            
            $customMessages = [
                'mobile.required' => 'رقم الهاتف مطلوب.',
                'mobile.unique' => 'هذا الرقم مسجل بالفعل. يرجى تسجيل الدخول.',
                'email.required' => 'البريد الإلكتروني مطلوب.',
                'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
                'code.exists' => 'رمز الدعوة غير صحيح.'
            ];
            
            $validator = Validator::make($request->all(), $validationRules, $customMessages);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            $type = $request->type;
            $firebase_id = $request->firebase_id;
            $referralAttempt = null;

            if ($type === 'phone') {
                return $this->handleDeferredPhoneSignup($request, $firebase_id);
            }

            if ($request->filled('email')) {
                $request->merge(['email' => Str::lower(trim($request->email))]);
            }

            if ($type === 'phone' && empty($request->email)) {
                $generatedEmail = $this->generatePhoneSignupEmail(
                    $request->country_code,
                    $request->mobile
                );

                $request->merge(['email' => $generatedEmail]);

                \Log::info('Generated fallback email for phone signup', [
                    'mobile' => $request->mobile,
                    'country_code' => $request->country_code,
                    'generated_email' => $generatedEmail,
                ]);
            }

            $existingGoogleUser = null;
            if ($type == 'google') {
                $existingGoogleUser = SocialLogin::where('firebase_id', $firebase_id)
                    ->where('type', 'google')
                    ->with('user')
                    ->first();
                    
                \Log::info('Searching for existing Google user by firebase_id:', [
                    'firebase_id' => $firebase_id,
                    'found' => $existingGoogleUser ? 'yes' : 'no',
                    'user_id' => $existingGoogleUser ? $existingGoogleUser->user->id : null
                ]);
            }
            
            $socialLogin = SocialLogin::where('firebase_id', $firebase_id)->where('type', $type)->with('user', function ($q) {
                $q->withTrashed();
            })->whereHas('user', function ($q) {
                $q->role('User');
            })->first();
            
            if (!empty($socialLogin->user->deleted_at)) {
                ResponseService::errorResponse('تم تعطيل الحساب. يرجى التواصل مع الإدارة.', null, config('constants.RESPONSE_CODE.DEACTIVATED_ACCOUNT'));
                }

                Auth::guard('web')->login($socialLogin->user);
                $auth = Auth::user();
            }

            if (!$auth->hasRole('User')) {
                ResponseService::errorResponse('تم تعطيل الحساب. يرجى التواصل مع الإدارة.', null, config('constants.RESPONSE_CODE.DEACTIVATED_ACCOUNT'));
            }

            $user->password = Hash::make($request->password);
            $user->save();

            Auth::guard('web')->login($user);
            
            $token = $user->createToken($user->name ?? '')->plainTextToken;

            if (!empty($request->fcm_id)) {
                UserFcmToken::updateOrCreate(
                    ['fcm_token' => $request->fcm_id],
                    [
                        'user_id'          => $user->id,
                        'platform_type'    => $request->platform_type ?? 'android',
                        'last_activity_at' => Carbon::now(),
                    ]
                );
            }

            return ResponseService::successResponse('تمت العملية بنجاح.');
            
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> updatePassword");
            ResponseService::errorResponse();
        }
    }


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
     * 
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
            
                 $referral = Referral::create([
                'referrer_id' => $referrer->id,
                'referred_user_id' => $user->id,
                'challenge_id' => $challenge->id,
                'points' => $challenge->points_per_referral,
            ]);
            
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
    public function getUserProfileStats(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return ResponseService::errorResponse('User not authenticated', null, 401);
            }
            
            $totalAds = Item::where('user_id', $user->id)->count();
            $activeAds = Item::where('user_id', $user->id)->where('status', 'approved')->count();
            
            $totalFavorites = Favourite::where('user_id', $user->id)->count();
            
            $totalChats = Chat::whereHas('itemOffer', function ($query) use ($user) {

                    $query->where('seller_id', $user->id)
                        ->orWhere('buyer_id', $user->id);

                })
                ->whereHas('messages')
                ->count();
            
            $stats = [
                'total_ads' => $totalAds,
                'active_ads' => $activeAds,
                'total_favorites' => $totalFavorites,
                'total_chats' => $totalChats,
            ];
            
            ResponseService::successResponse('User statistics retrieved successfully', $stats);
            
        } catch (\Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getUserProfileStats");
            ResponseService::errorResponse();
        }
    }

    /**
     */
    public function saveUserLocation(Request $request) {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'error' => true,
                    'message' => 'Unauthorized'
                ], 401);
            }

            if ($user->user_type != 1) {
                return response()->json([
                    'error' => true,
                    'message' => 'Location saving is only available for individual accounts'
                ], 400);
            }

            $request->validate([
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
                'area' => 'nullable|string',
                'city' => 'nullable|string',
                'state' => 'nullable|string',
                'country' => 'nullable|string',
            ]);

            $user->update([
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'area' => $request->area,
                'city' => $request->city,
                'state' => $request->state,
                'country' => $request->country,
                'updated_at' => now(),
            ]);

            return response()->json([
                'error' => false,
                'message' => 'Location saved successfully',
                'data' => [
                    'latitude' => $user->latitude,
                    'longitude' => $user->longitude,
                    'area' => $user->area,
                    'city' => $user->city,
                    'state' => $user->state,
                    'country' => $user->country,
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'error' => true,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while saving location: ' . $e->getMessage()
            ], 500);
        }
    }




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







    public function getManualBanks(Request $request) {
        try {
            $perPage = $this->resolvePerPage($request, 15, 100);


            $availableColumns = Schema::getColumnListing('manual_banks');

            $columns = array_values(array_intersect($availableColumns, [
                'id',
                'name',
                'logo_path',
                'beneficiary_name',
                'account_name',
                'account_number',
                'iban',
                'swift',
                'branch',
                'note',
                'notes',
                'display_order',
                'status',
                'currency',
                'qr_code_path',
                'created_at',
                'updated_at',
            ]));

            if (!in_array('id', $columns, true)) {
                $columns[] = 'id';
            }


            $banks = ManualBank::active()
                ->select($columns)
                ->orderBy('display_order')
                ->orderBy('id')
                ->paginate($perPage)
                ->appends($request->query());

            $banks->getCollection()->transform(function (ManualBank $bank) {


                $bankData = $bank->toArray();

                if (empty($bankData['bank_name']) && isset($bankData['name'])) {
                    $bankData['bank_name'] = $bankData['name'];
                }

                if ((empty($bankData['account_name']) || ! is_string($bankData['account_name']))
                    && ! empty($bankData['beneficiary_name'])) {
                    $bankData['account_name'] = $bankData['beneficiary_name'];
                }

                if ((empty($bankData['beneficiary_name']) || ! is_string($bankData['beneficiary_name']))
                    && ! empty($bankData['account_name'])) {
                    $bankData['beneficiary_name'] = $bankData['account_name'];
                }

                if (empty($bankData['account_number']) && ! empty($bankData['iban'])) {
                    $bankData['account_number'] = $bankData['iban'];
                }

                foreach ($bankData as $key => $value) {
                    if (!is_string($value) || $value === '') {
                        continue;
                    }

                    if (str_contains($key, 'path') || str_contains($key, 'image') || str_contains($key, 'logo') || str_contains($key, 'qr')) {
                        $bankData[$key . '_url'] = $this->generateManualPaymentSignedUrl($value);
                    }
                }

                return $bankData;
            });

            ResponseService::successResponse("Manual Banks Fetched", $banks);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getManualBanks");
            ResponseService::errorResponse();
        }
    }

    public function storeManualPaymentRequest(Request $request) {
        if ($request->filled('bank_id') && !$request->filled('manual_bank_id')) {
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

    public function getManualPaymentRequests(Request $request) {
        $filters = $this->normalizeManualPaymentRequestFilters($request->all());


        $gatewayAliasMap = $this->manualPaymentGatewayAliasMap();
        $gatewayValidationValues = array_values(array_unique(array_merge(
            array_keys($gatewayAliasMap),
            array_merge(...array_values($gatewayAliasMap))
        )));


        $validator = Validator::make($filters, [
            'status' => ['nullable', Rule::in([
                ManualPaymentRequest::STATUS_PENDING,
                ManualPaymentRequest::STATUS_UNDER_REVIEW,
                ManualPaymentRequest::STATUS_APPROVED,
                ManualPaymentRequest::STATUS_REJECTED,
            ])],
            'payment_gateway' => ['nullable', Rule::in($gatewayValidationValues)],
            'department' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],


        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        $validated = $validator->validated();


        $status = $validated['status'] ?? $filters['status'] ?? null;
        $paymentGateway = $this->normalizeManualPaymentGateway(
            $validated['payment_gateway'] ?? $filters['payment_gateway'] ?? null
        );
        
        $department = array_key_exists('department', $validated)
            ? $validated['department']
            : ($filters['department'] ?? null);

        $page = (int) ($validated['page'] ?? $filters['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? $filters['per_page'] ?? 15);

        if ($perPage < 1) {
            $perPage = 15;
        } elseif ($perPage > 100) {
            $perPage = 100;
        }


        try {
            $query = ManualPaymentRequest::query()
                ->with([
                    'manualBank',
                    'payable',
                    'paymentTransaction.order',
                ]);

            $this->applyManualPaymentRequestVisibilityScope($query, (int) Auth::id());

            $query
                ->when($status, static function ($builder, string $statusValue) {
                    $builder->where('manual_payment_requests.status', $statusValue);


                })
                ->when($paymentGateway, function ($builder, string $gateway) {
                    $aliases = $this->expandManualPaymentGatewayAliases($gateway);

                    $builder->where(static function ($query) use ($aliases, $gateway) {
                        $query->whereHas('paymentTransaction', static function ($transactionQuery) use ($aliases) {
                            $transactionQuery->whereIn('payment_gateway', $aliases);
                        });

                        if (in_array($gateway, ['manual_banks', 'manual_bank'], true)) {
                            $query->orWhereDoesntHave('paymentTransaction');
                        }
                    });
                })
                ->when(
                    $department !== null,
                    static function ($builder) use ($department) {
                        $builder->where(static function ($query) use ($department) {
                            $query->where('manual_payment_requests.department', $department)
                                ->orWhereNull('manual_payment_requests.department');
                        });
                    }
                )
                ->orderByDesc('manual_payment_requests.id');
            $stats = $this->summarizeManualPaymentRequests($query);

            $paginator = $query->paginate($perPage, ['manual_payment_requests.*'], 'page', $page);

            $requests = ManualPaymentRequestResource::collection(collect($paginator->items()))->resolve();

            $meta = [
                'total' => (int) $paginator->total(),
                'current_page' => (int) $paginator->currentPage(),
                'last_page' => (int) max($paginator->lastPage(), 1),
                'per_page' => (int) $paginator->perPage(),
            ];

            ResponseService::successResponse(
                'أ¢â€¢ع¾ط·آ²أ¢â€‌ع©ط£آ  أ¢â€¢ع¾ط·آ´أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ° أ¢â€¢ع¾أ¢â€¢â€“أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط·آ°أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾ط·آ² أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط¢ظ¾أ¢â€¢ع¾أ¢â€¢آ£ أ¢â€¢ع¾ط·آ¯أ¢â€‌ع©ط¢â€‍أ¢â€‌ع©ط£آ¨أ¢â€¢ع¾ط¢آ»أ¢â€‌ع©ط£ع¾أ¢â€‌ع©ط£آ¨ أ¢â€¢ع¾ط·آ°أ¢â€‌ع©ط¢â€ أ¢â€¢ع¾ط·آ´أ¢â€¢ع¾ط·آ¯أ¢â€¢ع¾ط·آµ',
                [
                    'manual_payment_requests' => $requests,
                    'items' => $requests,
                    'meta' => $meta,
                    'stats' => $stats,

                ]
            );
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getManualPaymentRequests');
            ResponseService::errorResponse();
        }
    }

    public function showManualPaymentRequest($manualPaymentRequestId) {
        try {
            $manualPaymentRequest = ManualPaymentRequest::with(['manualBank', 'paymentTransaction.order', 'payable'])
                ->where('user_id', Auth::user()->id)
                ->findOrFail($manualPaymentRequestId);

            ResponseService::successResponse(
                'Manual Payment Request Fetched',
                ManualPaymentRequestResource::make($manualPaymentRequest)->resolve()
            );
        } catch (ModelNotFoundException) {
            ResponseService::errorResponse('Manual payment request not found.');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> showManualPaymentRequest');
            ResponseService::errorResponse();
        }
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
            ResponseService::errorResponse('رمز التحقق غير صحيح.', null, 404);
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






