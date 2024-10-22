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

            // Arka plan fotoğrafının yolu
            $backgroundPath = public_path('background.png');
            $uploadedPhotoPath = storage_path('app/' . $lastPhoto->path);

            // Dosya kontrolü
            if (!file_exists($backgroundPath)) {
                throw new \Exception('Background image not found at: ' . $backgroundPath);
            }

            // Yüklenen fotoğrafın varlığını kontrol edelim ve alternatif yolları deneyelim
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
                    throw new \Exception('Uploaded photo not found');
                }
            }

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

            // Beyaz alana tam sığması için en-boy oranını koruyarak yeni boyutları hesaplayalım
            $scaleWidth = $bgWidth / $upWidth;
            $scaleHeight = $bgHeight / $upHeight;
            $scale = min($scaleWidth, $scaleHeight) * 0.95; // %95 doluluk oranı

            $newWidth = (int)($upWidth * $scale);
            $newHeight = (int)($upHeight * $scale);

            // Merkez koordinatlarını hesaplayalım
            $destX = (int)(($bgWidth - $newWidth) / 2);
            $destY = (int)(($bgHeight - $newHeight) / 2);

            // Yeni resim oluşturalım
            $newImage = imagecreatetruecolor($bgWidth, $bgHeight);

            // PNG için alfa kanalını ayarlayalım
            imagealphablending($newImage, true);
            imagesavealpha($newImage, true);

            // Resimleri birleştirelim
            imagecopy($newImage, $background, 0, 0, 0, 0, $bgWidth, $bgHeight);
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

            // Önceki birleştirilmiş fotoğrafları temizleyelim
            $oldFiles = glob(public_path('storage/photos/merged_*.png'));
            foreach ($oldFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }

            // Yeni resmi kaydedelim
            $newFileName = 'merged_' . time() . '.png';
            $newFilePath = public_path('storage/photos/' . $newFileName);

            // Klasör kontrolü
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
                'url' => asset('storage/photos/' . $newFileName)
            ]);

        } catch (\Exception $e) {
            Log::error('Photo merge error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
