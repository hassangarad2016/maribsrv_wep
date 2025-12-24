<?php

namespace App\Http\Controllers\Api\Sections\Services\TransformServiceFieldsSchema;

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

trait TransformServiceFieldsSchemaTrait
{
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
}