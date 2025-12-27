<?php

namespace App\Services;

use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Intervention\Image\Facades\Image;
use RuntimeException;

class FileService {
    private const WEBP_QUALITY = 85;
    /**
     * @param $requestFile
     * @param $folder
     * @return string
     */
    public static function compressAndUpload($requestFile, $folder) {
        if (self::isImageUpload($requestFile)) {
            return self::storeWebpImage($requestFile, $folder);
        }

        $file_name = uniqid('', true) . time() . '.' . $requestFile->getClientOriginalExtension();
        $file = $requestFile;
        $file->storeAs($folder, $file_name, 'public');
        return $folder . '/' . $file_name;
    }


    /**
     * @param $requestFile
     * @param $folder
     * @return string
     */
    public static function upload($requestFile, $folder) {
        if (self::isImageUpload($requestFile)) {
            return self::storeWebpImage($requestFile, $folder);
        }

        $file_name = uniqid('', true) . time() . '.' . $requestFile->getClientOriginalExtension();
        $requestFile->storeAs($folder, $file_name, 'public');
        return $folder . '/' . $file_name;
    }

    /**
     * @param $requestFile
     * @param $folder
     * @param $deleteRawOriginalImage
     * @return string
     */
    public static function replace($requestFile, $folder, $deleteRawOriginalImage) {
        self::delete($deleteRawOriginalImage);
        return self::upload($requestFile, $folder);
    }

    /**
     * @param $requestFile
     * @param $folder
     * @param $deleteRawOriginalImage
     * @return string
     */
    public static function compressAndReplace($requestFile, $folder, $deleteRawOriginalImage) {
        if (!empty($deleteRawOriginalImage)) {
            self::delete($deleteRawOriginalImage);
        }
        return self::compressAndUpload($requestFile, $folder);
    }


    /**
     * @param $requestFile
     * @param $code
     * @return string
     */
    public static function uploadLanguageFile($requestFile, $code) {
        $filename = $code . '.' . $requestFile->getClientOriginalExtension();
        if (file_exists(base_path('resources/lang/') . $filename)) {
            File::delete(base_path('resources/lang/') . $filename);
        }
        $requestFile->move(base_path('resources/lang/'), $filename);
        return $filename;
    }

    /**
     * @param $file
     * @return bool
     */
    public static function deleteLanguageFile($file) {
        if (file_exists(base_path('resources/lang/') . $file)) {
            return File::delete(base_path('resources/lang/') . $file);
        }
        return true;
    }


    /**
     * @param $image = rawOriginalPath
     * @return bool
     */
    public static function delete($image) {
        if (!empty($image) && Storage::disk(config('filesystems.default'))->exists($image)) {
            return Storage::disk(config('filesystems.default'))->delete($image);
        }

        //Image does not exist in server so feel free to upload new image
        return true;
    }

    /**
     * @throws Exception
     */
    public static function compressAndUploadWithWatermark($requestFile, $folder) {
        try {
            if (self::isImageUpload($requestFile)) {
                $watermarkPath = Setting::where('name', 'watermark_image')->value('value');
                $fullWatermarkPath = storage_path('app/public/' . $watermarkPath);

                return self::storeWebpImage($requestFile, $folder, static function ($image) use ($watermarkPath, $fullWatermarkPath) {
                    $watermark = null;
                    $imageWidth = $image->width();
                    $imageHeight = $image->height();

                    if (!empty($watermarkPath) && file_exists($fullWatermarkPath)) {
                        $watermark = Image::make($fullWatermarkPath)
                            ->resize($imageWidth, $imageHeight, function ($constraint) {
                                $constraint->aspectRatio();
                            })
                            ->opacity(10);
                    }

                    if ($watermark) {
                        $image->insert($watermark, 'center');
                    }
                });
            }

            $file_name = uniqid('', true) . time() . '.' . $requestFile->getClientOriginalExtension();
            $file = $requestFile;
            $file->storeAs($folder, $file_name, 'public');
            return $folder . '/' . $file_name;

        } catch (Exception $e) {
            throw new RuntimeException($e);
            //            $file = $requestFile;
            //            return  $file->storeAs($folder, $file_name, 'public');
        }
    }
    public static function compressAndReplaceWithWatermark($requestFile, $folder, $deleteRawOriginalImage = null)
{

    if (!empty($deleteRawOriginalImage)) {
        self::delete($deleteRawOriginalImage);
    }

    try {
        if (self::isImageUpload($requestFile)) {
            $watermarkPath = Setting::where('name', 'watermark_image')->value('value');
            $fullWatermarkPath = storage_path('app/public/' . $watermarkPath);

            return self::storeWebpImage($requestFile, $folder, static function ($image) use ($watermarkPath, $fullWatermarkPath) {
                $watermark = null;
                $imageWidth = $image->width();
                $imageHeight = $image->height();

                if (!empty($watermarkPath) && file_exists($fullWatermarkPath)) {
                    $watermark = Image::make($fullWatermarkPath)
                        ->resize($imageWidth, $imageHeight, function ($constraint) {
                            $constraint->aspectRatio();
                        })
                        ->opacity(10);
                }

                if ($watermark) {
                    $image->insert($watermark, 'center');
                }
            });
        }

        $file_name = uniqid('', true) . time() . '.' . $requestFile->getClientOriginalExtension();
        $file = $requestFile;
        $file->storeAs($folder, $file_name, 'public');
        return $folder . '/' . $file_name;

    } catch (Exception $e) {
        throw new RuntimeException($e);
    }
}

    private static function isImageUpload($requestFile): bool
    {
        if (! $requestFile instanceof UploadedFile) {
            return false;
        }

        $mimeType = $requestFile->getMimeType();
        if (is_string($mimeType)) {
            $normalized = strtolower($mimeType);
            if ($normalized === 'image/svg+xml') {
                return false;
            }
            if (str_starts_with($normalized, 'image/')) {
                return true;
            }
        }

        $extension = strtolower($requestFile->getClientOriginalExtension());
        if ($extension === 'svg') {
            return false;
        }
        return in_array($extension, [
            'jpg',
            'jpeg',
            'png',
            'webp',
            'gif',
            'bmp',
            'tif',
            'tiff',
            'avif',
            'heic',
            'heif',
        ], true);
    }

    private static function storeWebpImage(UploadedFile $requestFile, string $folder, ?callable $mutator = null): string
    {
        $file_name = uniqid('', true) . time() . '.webp';
        $imagePath = $requestFile->getPathname();
        if (!file_exists($imagePath) || !is_readable($imagePath)) {
            throw new RuntimeException("Uploaded image file is not readable at path: " . $imagePath);
        }

        $image = Image::make($imagePath)->orientate();

        if ($mutator !== null) {
            $mutator($image);
        }

        Storage::disk(config('filesystems.default'))->put(
            $folder . '/' . $file_name,
            (string) $image->encode('webp', self::WEBP_QUALITY)
        );

        return $folder . '/' . $file_name;
    }

}
