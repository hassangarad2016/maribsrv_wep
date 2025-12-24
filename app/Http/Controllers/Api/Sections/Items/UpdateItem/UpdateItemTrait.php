<?php

namespace App\Http\Controllers\Api\Sections\Items\UpdateItem;

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

trait UpdateItemTrait
{
    public function updateItem(Request $request) {

        $categoryInput = $request->input('category_id');
        $item = null;

        if (! is_numeric($categoryInput) && $request->filled('id')) {
            $item = Item::owner()->find($request->input('id'));
            $categoryInput = $item?->category_id;
        }

        $categoryId = is_numeric($categoryInput) ? (int) $categoryInput : null;
        $requiresProductLink = $this->shouldRequireProductLink($categoryId);

        $validator = Validator::make($request->all(), [
            'id'                   => 'required',
            'name'                 => 'nullable',
            // 'slug'                 => 'regex:/^[a-z0-9-]+$/',
            'price'                => 'nullable',
            'description'          => 'nullable',
            'latitude'             => 'nullable',
            'longitude'            => 'nullable',
            'address'              => 'nullable',
            'contact'              => 'nullable',
            'image'                => 'nullable|mimes:jpeg,jpg,png|max:4096',
            'custom_fields'        => 'nullable',
            'custom_field_files'   => 'nullable|array',
            'custom_field_files.*' => 'nullable|mimes:jpeg,png,jpg,pdf,doc|max:4096',
            'gallery_images'       => 'nullable|array',
            'currency'             => 'required',
            'product_link'         => [
                'nullable',
                'url',
                'max:2048',
                Rule::requiredIf($requiresProductLink),
            ],
            'review_link'          => 'nullable|url|max:2048',
        
         ]);

        if ($validator->fails()) {
            ResponseService::validationErrors($validator->errors());

        }


        $item ??= Item::owner()->findOrFail($request->id);

        if ($categoryId === null) {
            $categoryId = (int) $item->category_id;
        }

        $currentSection = $this->resolveSectionByCategoryId($item->category_id);
        $updateAuthorization = Gate::inspect('section.update', $currentSection);

        if ($updateAuthorization->denied()) {
            $message = $updateAuthorization->message() ?? SectionDelegatePolicy::FORBIDDEN_MESSAGE;

            ResponseService::errorResponse($message, null, 403);
        }

        $targetSection = $this->resolveSectionByCategoryId($categoryId);

        if ($targetSection !== $currentSection) {
            $changeAuthorization = Gate::inspect('section.change', [$currentSection, $targetSection]);

            if ($changeAuthorization->denied()) {
                $message = $changeAuthorization->message() ?? SectionDelegatePolicy::FORBIDDEN_MESSAGE;

                ResponseService::errorResponse($message, null, 403);
            }
        }


        DB::beginTransaction();

        try {


            // $slug = $request->input('slug', $item->slug);
            // $uniqueSlug = HelperService::generateUniqueSlug(new Item(), $slug,$request->id);

            $data = $request->all();

            $explicitInterfaceType = InterfaceSectionService::canonicalSectionTypeOrNull(
                $request->input('interface_type')
            );
            $resolvedInterfaceType = $explicitInterfaceType
                ?? $this->resolveInterfaceSectionForCategory($categoryId)
                ?? InterfaceSectionService::canonicalSectionTypeOrNull($targetSection);

            if ($resolvedInterfaceType !== null) {
                $data['interface_type'] = $resolvedInterfaceType;
            } elseif (array_key_exists('interface_type', $data)) {
                unset($data['interface_type']);
            }


           if (array_key_exists('price', $data)) {
                $priceInput = $data['price'];
                if ($priceInput === null || $priceInput === '') {
                    unset($data['price']);
                } else {
                    $normalizedPrice = $priceInput;
                    if (is_string($priceInput)) {
                        $normalizedPrice = preg_replace(
                            '/[^0-9.]/',
                            '',
                            str_replace(',', '', $priceInput)
                        );
                    }

                    if ($normalizedPrice === null || $normalizedPrice === '') {
                        unset($data['price']);
                    } else {
                        $data['price'] = (float) $normalizedPrice;
                    }
                }
            }

            $data['product_link'] = $request->filled('product_link') ? $request->input('product_link') : null;
            $data['review_link'] = $request->filled('review_link') ? $request->input('review_link') : null;


            // $data['slug'] = $uniqueSlug;
            if ($request->hasFile('image')) {
                try {
                    $variants = ImageVariantService::storeWithVariants($request->file('image'), $this->uploadFolder);
                } catch (Throwable $exception) {
                    ResponseService::validationErrors([
                        'image' => [__('Unable to process the uploaded image. Please try again with a different file.')],
                    ]);
                }

                ImageVariantService::deleteStoredVariants([
                    $item->getRawOriginal('image'),
                    $item->getRawOriginal('thumbnail_url'),
                    $item->getRawOriginal('detail_image_url'),
                ]);

                $data['image'] = $variants['original'];
                $data['thumbnail_url'] = $variants['thumbnail'];
                $data['detail_image_url'] = $variants['detail'];
            
            }

            $item->update($data);

            //Update Custom Field values for item
            if ($request->custom_fields) {
                $itemCustomFieldValues = [];
                // Handle both JSON string and array formats
                $customFields = is_string($request->custom_fields) 
                    ? json_decode($request->custom_fields, true, 512, JSON_THROW_ON_ERROR) 
                    : $request->custom_fields;
                    
                foreach ($customFields as $key => $custom_field) {
                    $itemCustomFieldValues[] = [
                        'item_id'         => $item->id,
                        'custom_field_id' => $key,
                        'value'           => json_encode($custom_field, JSON_THROW_ON_ERROR),
                        'updated_at'      => now()

                    ];
                }

                if (count($itemCustomFieldValues) > 0) {
                    ItemCustomFieldValue::upsert($itemCustomFieldValues, ['item_id', 'custom_field_id'], ['value', 'updated_at']);
                }
            }

            //Add new gallery images
            if ($request->hasFile('gallery_images')) {
                $galleryImages = [];
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
                        'created_at' => time(),
                        'updated_at' => time(),
                    ];
                }
                if (count($galleryImages) > 0) {
                    ItemImages::insert($galleryImages);
                }
            }

            if ($request->custom_field_files) {
                $itemCustomFieldValues = [];
                foreach ($request->custom_field_files as $key => $file) {
                    $value = ItemCustomFieldValue::where(['item_id' => $item->id, 'custom_field_id' => $key])->first();
                    if (!empty($value)) {
                        $file = FileService::replace($file, 'custom_fields_files', $value->getRawOriginal('value'));
                    } else {
                        $file = '';
                    }
                    $itemCustomFieldValues[] = [
                        'item_id'         => $item->id,
                        'custom_field_id' => $key,
                        'value'           => $file,
                        'updated_at'      => time()
                    ];
                }

                if (count($itemCustomFieldValues) > 0) {
                    ItemCustomFieldValue::upsert($itemCustomFieldValues, ['item_id', 'custom_field_id'], ['value', 'updated_at']);
                }
            }

            //Delete gallery images
            if (!empty($request->delete_item_image_id)) {
                $item_ids = explode(',', $request->delete_item_image_id);
                foreach (ItemImages::whereIn('id', $item_ids)->get() as $itemImage) {
                    ImageVariantService::deleteStoredVariants([
                        $itemImage->getRawOriginal('image'),
                        $itemImage->getRawOriginal('thumbnail_url'),
                        $itemImage->getRawOriginal('detail_image_url'),
                    ]);
                    
                    $itemImage->delete();
                }
            }

            $result = Item::with('user:id,name,email,mobile,profile,country_code', 'category:id,name,image', 'gallery_images:id,image,item_id,thumbnail_url,detail_image_url', 'featured_items', 'favourites', 'item_custom_field_values.custom_field', 'area')->where('id', $item->id)->get();
            /*
             * Collection does not support first OR find method's result as of now. It's a part of R&D
             * So currently using this shortcut method
            */
            $result = new ItemCollection($result);


            DB::commit();
            ResponseService::successResponse("Item Fetched Successfully", $result);
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, "API Controller -> updateItem");
            ResponseService::errorResponse();
        }
    }
}