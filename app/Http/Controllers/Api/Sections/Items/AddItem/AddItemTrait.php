<?php

namespace App\Http\Controllers\Api\Sections\Items\AddItem;

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

trait AddItemTrait
{
     public function addItem(Request $request) {
        try {


            if ($request->filled('custom_fields') && is_string($request->input('custom_fields'))) {
                try {
                    $decodedCustomFields = json_decode($request->input('custom_fields'), true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $exception) {
                    ResponseService::validationErrors([
                        'custom_fields' => [
                            __('validation.json', ['attribute' => __('Custom Fields')]),
                        ],
                    ]);
                }

                if (! is_array($decodedCustomFields)) {
                    ResponseService::validationErrors([
                        'custom_fields' => [
                            __('validation.array', ['attribute' => __('Custom Fields')]),
                        ],
                    ]);
                }

                $request->merge(['custom_fields' => $decodedCustomFields]);
            }

            $categoryInput = $request->input('category_id');
            $categoryId = is_numeric($categoryInput) ? (int) $categoryInput : null;
            $requiresProductLink = $this->shouldRequireProductLink($categoryId);

            $allowedCustomFieldIds = collect();
            $categoryIdsForFields = [];

            if ($categoryId !== null) {
                $categoryIdsForFields[] = $categoryId;
            }

            if ($request->filled('all_category_ids')) {
                $rawCategoryIds = $request->input('all_category_ids');
                if (is_array($rawCategoryIds)) {
                    $categoryIdsForFields = array_merge($categoryIdsForFields, $rawCategoryIds);
                } elseif (is_string($rawCategoryIds)) {
                    preg_match_all('/\d+/', $rawCategoryIds, $matches);
                    if (! empty($matches[0])) {
                        $categoryIdsForFields = array_merge($categoryIdsForFields, $matches[0]);
                    }
                }
            }

            $categoryIdsForFields = array_values(array_unique(array_filter(array_map('intval', $categoryIdsForFields))));

            if (! empty($categoryIdsForFields)) {
                $categoryCollection = Category::query()
                    ->with(['custom_fields' => static function ($query) {
                        $query->select('id', 'category_id', 'custom_field_id');
                    }])
                    ->whereIn('id', $categoryIdsForFields)
                    ->get();

                if ($categoryCollection->isNotEmpty()) {
                    $allowedCustomFieldIds = $categoryCollection
                        ->pluck('custom_fields')
                        ->flatten()
                        ->pluck('custom_field_id')
                        ->filter(static fn ($id) => $id !== null)
                        ->map(static fn ($id) => (int) $id)
                        ->unique()
                        ->values();
                }
            }


            $validationRules = [

                'name'                 => 'required',
                'category_id'          => 'required|integer',
                'price'                => 'required',
                'description'          => 'required',
                'latitude'             => 'required',
                'longitude'            => 'required',
                'address'              => 'required',
                'contact'              => 'numeric',
                'show_only_to_premium' => 'required|boolean',
                'video_link'           => 'nullable|url',
                'gallery_images'       => 'nullable|array|min:1',
                'gallery_images.*'     => 'nullable|mimes:jpeg,png,jpg,webp|max:4096',
                'image'                => 'required|mimes:jpeg,png,jpg,webp|max:4096',
                'country'              => 'required',
                'state'                => 'nullable',
                'city'                 => 'required',
                'area_id'              => 'nullable',
                'custom_field_files'   => 'nullable|array',
                'custom_field_files.*' => 'nullable|mimes:jpeg,png,jpg,webp,pdf,doc|max:4096',
                'custom_fields'        => 'nullable|array',
                'custom_fields.*'      => 'nullable',
                'slug'                 => 'nullable|regex:/^[a-z0-9-]+$/',
                'currency'             => 'required',

                'product_link'         => [
                    'nullable',
                    'url',
                    'max:2048',
                    Rule::requiredIf($requiresProductLink),
                ],
                'review_link'          => 'nullable|url|max:2048',

            ];

            if ($categoryId !== null && $this->isGeoDisabledCategory($categoryId)) {
                foreach (['latitude', 'longitude', 'city', 'area_id', 'address', 'country', 'state'] as $geoField) {
                    $validationRules[$geoField] = 'nullable';
                }
                $validationRules['image'] = 'nullable|mimes:jpeg,png,jpg,webp|max:4096';
            }

            $validator = Validator::make($request->all(), $validationRules);





            if ($validator->fails()) {
                ResponseService::validationErrors($validator->errors());
            }


            $section = $this->resolveSectionByCategoryId((int) $request->category_id);
            $authorization = Gate::inspect('section.publish', $section);

            if ($authorization->denied()) {
                $message = $authorization->message() ?? SectionDelegatePolicy::FORBIDDEN_MESSAGE;

                ResponseService::errorResponse($message, null, 403);
            }


            DB::beginTransaction();
            $user = Auth::user();



            
            $user_package = UserPurchasedPackage::onlyActive()
                ->whereHas('package', static function ($q) {
                    $q->where('type', 'item_listing');
                })
                ->lockForUpdate()
                ->first();



            if (empty($user_package)) {
                DB::rollBack();
                ResponseService::errorResponse("No Active Package found for Item Creation");
            }


            // Generate a unique slug if the slug is not provided
            $slug = $request->input('slug');
            if (empty($slug)) {
                $slug = HelperService::generateRandomSlug();
            }
            $uniqueSlug = HelperService::generateUniqueSlug(new Item(), $slug);
            if ($uniqueSlug === '') {
                $uniqueSlug = Str::lower(Str::random(12));
            }
            $status = $this->resolveInitialItemStatus($user, $section);

            $data = Arr::only($request->all(), [
                'category_id',
                'price',
                'description',
                'latitude',
                'longitude',
                'address',
                'contact',
                'show_only_to_premium',
                'video_link',
                'country',
                'state',
                'city',
                'area_id',
                'all_category_ids',
                'interface_type',
            ]);

            $categoryIdValue = null;
            if ($request->filled('category_id') && is_numeric($request->input('category_id'))) {
                $categoryIdValue = (int) $request->input('category_id');
            }
            $explicitInterfaceType = InterfaceSectionService::canonicalSectionTypeOrNull(
                $request->input('interface_type')
            );
            $resolvedInterfaceType = $explicitInterfaceType
                ?? $this->resolveInterfaceSectionForCategory($categoryIdValue)
                ?? InterfaceSectionService::canonicalSectionTypeOrNull($section);

            if ($resolvedInterfaceType !== null) {
                $data['interface_type'] = $resolvedInterfaceType;
            } else {
                unset($data['interface_type']);
            }

            

            $data['name'] = Str::upper($request->name);
            $data['slug'] = $uniqueSlug;
            $data['status'] = $status;
            $data['user_id'] = $user->id;
            $data['expiry_date'] = $user_package->end_date;
            $data['currency'] = $request->input('currency', 'YER');
            $data['show_only_to_premium'] = $request->boolean('show_only_to_premium');
            $data['product_link'] = $request->filled('product_link') ? $request->input('product_link') : null;
            $data['review_link'] = $request->filled('review_link') ? $request->input('review_link') : null;

            
        
            if ($request->hasFile('image')) {
                try {
                    $variants = ImageVariantService::storeWithVariants($request->file('image'), $this->uploadFolder);
                } catch (Throwable $exception) {
                    ResponseService::validationErrors([
                        'image' => [__('Unable to process the uploaded image. Please try again with a different file.')],
                    ]);
                }
            
            
            

                $data['image'] = $variants['original'];
                $data['thumbnail_url'] = $variants['thumbnail'];
                $data['detail_image_url'] = $variants['detail'];

            }
            $item = Item::create($data);

            if ($request->hasFile('gallery_images')) {
                $galleryImages = [];
                $timestamp = now();
                foreach ($request->file('gallery_images') as $file) {

                    try {
                        $galleryVariants = ImageVariantService::storeWithVariants($file, $this->uploadFolder);
                    } catch (Throwable $exception) {
                        ResponseService::validationErrors([
                            'gallery_images' => [__('Unable to process one of the gallery images. Please verify the files and retry.')],
                        ]);
                    }

                    $galleryImages[] = [
                        'image'      => $galleryVariants['original'],
                        'thumbnail_url' => $galleryVariants['thumbnail'],
                        'detail_image_url' => $galleryVariants['detail'],
                        'item_id'    => $item->id,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp->copy(),
                    ];
                }

                if (count($galleryImages) > 0) {
                    ItemImages::insert($galleryImages);
                }
            }

            if ($request->custom_fields) {
                $itemCustomFieldValues = [];
                // Handle both JSON string and array formats
                $customFields = is_string($request->custom_fields)
                    ? json_decode($request->custom_fields, true, 512, JSON_THROW_ON_ERROR)


                    : $request->custom_fields;
                    
                foreach ($customFields as $key => $custom_field) {


                    $customFieldId = is_numeric($key) ? (int) $key : null;

                    if ($customFieldId === null || ! $allowedCustomFieldIds->containsStrict($customFieldId)) {
                        ResponseService::validationErrors([
                            "custom_fields.$key" => [__('The selected custom field is invalid for this category.')],
                        ]);
                    }

                    if ($custom_field instanceof UploadedFile) {
                        ResponseService::validationErrors([
                            "custom_fields.$key" => [__('Custom field values cannot contain files. Use custom_field_files instead.')],
                        ]);
                    }

                    try {
                        $encodedValue = json_encode($custom_field, JSON_THROW_ON_ERROR);
                    } catch (JsonException $exception) {
                        ResponseService::validationErrors([
                            "custom_fields.$key" => [__('Unable to process the provided custom field value.')],
                        ]);
                    }

                    $timestamp = now();

                    $itemCustomFieldValues[] = [
                        'item_id'         => $item->id,
                        'custom_field_id' => $customFieldId,
                        'value'           => $encodedValue,
                        'created_at'      => $timestamp,
                        'updated_at'      => $timestamp->copy()
                    ];
                }

                if (count($itemCustomFieldValues) > 0) {
                    ItemCustomFieldValue::insert($itemCustomFieldValues);
                }
            }

            if ($request->custom_field_files) {
                $itemCustomFieldValues = [];
                foreach ($request->custom_field_files as $key => $file) {
                    $customFieldId = is_numeric($key) ? (int) $key : null;

                    if ($customFieldId === null || ! $allowedCustomFieldIds->containsStrict($customFieldId)) {
                        ResponseService::validationErrors([
                            "custom_field_files.$key" => [__('The selected custom field is invalid for this category.')],
                        ]);
                    }
 
                    if (! $file instanceof UploadedFile) {
                        
                        ResponseService::validationErrors([
                            "custom_field_files.$key" => [__('Each custom field file must be an uploaded file.')],
                        ]);
                    }

                    try {
                        $filePath = ! empty($file) ? FileService::upload($file, 'custom_fields_files') : '';
                    } catch (Throwable $exception) {
                        ResponseService::validationErrors([
                            "custom_field_files.$key" => [__('Failed to store the uploaded custom field file. Please try again.')],
                        ]);
                    }



                    $timestamp = now();

                    $itemCustomFieldValues[] = [

                        'item_id'         => $item->id,
                        'custom_field_id' => $customFieldId,
                        'value'           => $filePath,
                        'created_at'      => $timestamp,
                        'updated_at'      => $timestamp,
                    ];
                }

                if (count($itemCustomFieldValues) > 0) {
                    ItemCustomFieldValue::insert($itemCustomFieldValues);
                }
            }


            ++$user_package->used_limit;
            $user_package->save();



            // Add where condition here
            $result = Item::with(
                'user:id,name,email,mobile,profile,country_code',
                'category:id,name,image',
                'gallery_images:id,image,item_id,thumbnail_url,detail_image_url',
                'featured_items',
                'favourites',
                'item_custom_field_values.custom_field',
                'area'
            )->where('id', $item->id)->get();
            $result = new ItemCollection($result);

            DB::commit();
            ResponseService::successResponse("Item Added Successfully", $result);
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, "API Controller -> addItem");
            ResponseService::errorResponse();
        }
    }
}
