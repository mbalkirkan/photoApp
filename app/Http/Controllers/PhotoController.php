<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PhotoController extends Controller
{
    public function upload(Request $request)
    {
        try {
            // Fotoğrafın olup olmadığını kontrol edelim
            if (!$request->hasFile('photo')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No photo file received'
                ], 400);
            }

            // Fotoğrafı alalım
            $file = $request->file('photo');

            // Orijinal dosya adını ve uzantısını alalım
            $fileName = time() . '.' . $file->getClientOriginalExtension();

            // Fotoğrafı 'public/photos' dizinine kaydedelim
            $path = $file->storeAs('public/photos', $fileName);

            if (!$path) {
                throw new \Exception('Failed to save file');
            }

            $checked = $request->input('checked');

            // Veritabanına kaydedelim
            $photo = new \App\Models\Photo();
            $photo->path = $path;
            $photo->email = $request->input('email');
            $photo->checked = $checked == 'true';
            $photo->save();

            // Fotoğrafın başarıyla yüklendiğini belirten yanıtı döndürelim
            return response()->json([
                'success' => true,
                'path' => $path,
                'url' => Storage::url($path),
                'email' => $request->input('email'), // Gönderilen email verisini alalım
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function get(Request $request)
    {
        try {
            // Veritabanından fotoğrafları alalım
            $photos = \App\Models\Photo::all();

            // Fotoğrafları döndürelim
            return response()->json([
                'success' => true,
                'photos' => $photos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function createImageFromFile($filePath)
    {
        // Dosya tipini kontrol et
        $imageInfo = getimagesize($filePath);
        if ($imageInfo === false) {
            throw new \Exception('Invalid image file');
        }

        // MIME tipine göre uygun fonksiyonu kullan
        switch ($imageInfo['mime']) {
            case 'image/jpeg':
                return imagecreatefromjpeg($filePath);
            case 'image/png':
                return imagecreatefrompng($filePath);
            case 'image/gif':
                return imagecreatefromgif($filePath);
            default:
                throw new \Exception('Unsupported image format: ' . $imageInfo['mime']);
        }
    }

    public function getLastPhotoMerged()
    {
        try {
            // Son yüklenen fotoğrafı alalım
            $lastPhoto = Photo::latest()->first();

            if (!$lastPhoto) {
                return response()->json([
                    'success' => false,
                    'message' => 'No photos found'
                ], 404);
            }

            // Debug için dosya yollarını loglayalım
            \Log::info('Processing paths', [
                'original_path' => $lastPhoto->path,
                'storage_path' => storage_path(),
                'public_path' => public_path(),
            ]);

            // Arka plan fotoğrafının yolu
            $backgroundPath = public_path('background.png');

            // Dosyaların var olduğunu kontrol edelim
            if (!file_exists($backgroundPath)) {
                throw new \Exception('Background image not found at: ' . $backgroundPath);
            }

            // Yüklenen fotoğrafın yolunu düzenleyelim
            $uploadedPhotoPath = storage_path('app/' . $lastPhoto->path);

            // Dosyanın varlığını kontrol edelim ve alternatif yolları deneyelim
            if (!file_exists($uploadedPhotoPath)) {
                // Alternatif yol 1: Storage disk üzerinden
                if (Storage::exists($lastPhoto->path)) {
                    $uploadedPhotoPath = Storage::path($lastPhoto->path);
                }
                // Alternatif yol 2: Public disk üzerinden
                else if (Storage::disk('public')->exists(basename($lastPhoto->path))) {
                    $uploadedPhotoPath = Storage::disk('public')->path(basename($lastPhoto->path));
                }
                // Alternatif yol 3: Public klasöründen
                else if (file_exists(public_path('storage/' . basename($lastPhoto->path)))) {
                    $uploadedPhotoPath = public_path('storage/' . basename($lastPhoto->path));
                }
                else {
                    throw new \Exception('Uploaded photo not found. Tried paths: ' .
                        json_encode([
                            'original' => $uploadedPhotoPath,
                            'storage' => Storage::path($lastPhoto->path),
                            'public_storage' => Storage::disk('public')->path(basename($lastPhoto->path)),
                            'public' => public_path('storage/' . basename($lastPhoto->path))
                        ])
                    );
                }
            }

            // Debug için bulunan dosya yolunu loglayalım
            \Log::info('Found photo at path', ['path' => $uploadedPhotoPath]);

            // Resimleri yükleyelim
            $background = $this->createImageFromFile($backgroundPath);
            $uploadedPhoto = $this->createImageFromFile($uploadedPhotoPath);

            // PNG için alfa kanalını koruyalım
            imagealphablending($background, true);
            imagesavealpha($background, true);

            if ($uploadedPhoto === false || $background === false) {
                throw new \Exception('Failed to create image resource');
            }

            // Resimlerin boyutlarını alalım
            $bgWidth = imagesx($background);
            $bgHeight = imagesy($background);
            $upWidth = imagesx($uploadedPhoto);
            $upHeight = imagesy($uploadedPhoto);

            // Yüklenen fotoğrafı %70 oranında küçültelim
            $newWidth = (int)($upWidth * 0.7);
            $newHeight = (int)($upHeight * 0.7);

            // Merkez koordinatlarını hesaplayalım
            $destX = (int)(($bgWidth - $newWidth) / 2);
            $destY = (int)(($bgHeight - $newHeight) / 2);

            // Yeni boş resim oluşturalım
            $newImage = imagecreatetruecolor($bgWidth, $bgHeight);

            // PNG için alfa kanalını ayarlayalım
            imagealphablending($newImage, true);
            imagesavealpha($newImage, true);

            // Arka planı yeni resme kopyalayalım
            imagecopy($newImage, $background, 0, 0, 0, 0, $bgWidth, $bgHeight);

            // Yüklenen fotoğrafı arka planın üzerine yerleştirelim
            imagecopyresampled(
                $newImage,
                $uploadedPhoto,
                $destX,
                $destY,
                0,
                0,
                $newWidth,
                $newHeight,
                $upWidth,
                $upHeight
            );

            // Yeni resmi kaydedelim
            $newFileName = 'merged_' . time() . '.png';

            // Yeni resmi public/storage altına kaydedelim
            $newFilePath = public_path('storage/photos/' . $newFileName);

            // Klasörün var olduğundan emin olalım
            if (!file_exists(dirname($newFilePath))) {
                mkdir(dirname($newFilePath), 0755, true);
            }

            // PNG olarak kaydedelim
            imagepng($newImage, $newFilePath, 9);

            // Belleği temizleyelim
            imagedestroy($newImage);
            imagedestroy($background);
            imagedestroy($uploadedPhoto);

            return response()->json([
                'success' => true,
                'message' => 'Photo merged successfully',
                'url' => asset('storage/photos/' . $newFileName),
                'debug' => [
                    'original_path' => $lastPhoto->path,
                    'used_path' => $uploadedPhotoPath,
                    'new_path' => $newFilePath
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Photo merge error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'debug_trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
