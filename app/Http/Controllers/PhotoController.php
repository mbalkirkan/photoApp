<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

//Xu4k5z6_9
//gladiators@mbalkirkan.com
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

            // mail olarak fotoğrafı ortalanmış şekilde gönderelim

            $details = [
                'subject' => 'Your Photo from Laneige The Grove Pop-up!',
                'body' => '
                <html>
                <head>
                <title>Your Photo from Laneige The Grove Pop-up!</title>
                </head>
                <body>
                <p>Thanks for visiting our Laneige The Grove Pop-up!

Enclosed please find your image from our photo booth!  Don\'t forget to tag and follow @laneige_us !

            Hope you revisit us again soon!</p>
                <img src="' . url('/photo/merged?id=' . $photo->id) . '" alt="Your Photo" />
                </body>
                </html>
                '
            ];

            try {
                Mail::send([], [], function ($message) use ($details) {
                    $message->to($details['email'])
                        ->subject($details['subject'])
                        ->setBody($details['body'], 'text/html');
                });
            } catch (\Exception $e) {
                Log::error('Mail error:', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
//
//            Mail::raw($details['body'], function ($message) use ($details) {
//                $message->to('ekrembey435@gmail.com')
//                    ->subject($details['subject']);
//            });



            // Fotoğrafın başarıyla yüklendiğini belirten yanıtı döndürelim
            return response()->json([
                'success' => true,
                'path' => $path,
                'url' => Storage::url($path),
                'email' => $request->input('email'), // Gönderilen email verisini alalım
                'id' => $photo->id
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
            $photos = \App\Models\Photo::orderBy('created_at', 'desc')->get();

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

    private function createMergedImage($photo)
    {
        // Arka plan fotoğrafının yolu
        $backgroundPath = public_path('background.png');
        $uploadedPhotoPath = storage_path('app/' . $photo->path);

        // Dosya kontrolü
        if (!file_exists($backgroundPath)) {
            throw new \Exception('Background image not found at: ' . $backgroundPath);
        }

        // Yüklenen fotoğrafın varlığını kontrol edelim ve alternatif yolları deneyelim
        if (!file_exists($uploadedPhotoPath)) {
            // Alternatif yol 1: Storage disk üzerinden
            if (Storage::exists($photo->path)) {
                $uploadedPhotoPath = Storage::path($photo->path);
            } // Alternatif yol 2: Public disk üzerinden
            else if (Storage::disk('public')->exists(basename($photo->path))) {
                $uploadedPhotoPath = Storage::disk('public')->path(basename($photo->path));
            } // Alternatif yol 3: Public klasöründen
            else if (file_exists(public_path('storage/' . basename($photo->path)))) {
                $uploadedPhotoPath = public_path('storage/' . basename($photo->path));
            } else {
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
        $scale = min($scaleWidth, $scaleHeight) * 0.65; // %95 doluluk oranı

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

        // Belleği temizleyelim
        imagedestroy($background);
        imagedestroy($uploadedPhoto);

        return $newImage;
    }

    /**
     * JSON response dönen API endpoint'i
     */
    public function getMergedPhoto(Request $request)
    {
        try {
            // ID parametresini kontrol edelim
            $photoId = $request->input('id');

            // ID varsa o fotoğrafı, yoksa son fotoğrafı alalım
            if ($photoId) {
                $photo = Photo::find($photoId);
                if (!$photo) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Photo not found with ID: ' . $photoId
                    ], 404);
                }
            } else {
                $photo = Photo::latest()->first();
                if (!$photo) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No photos found'
                    ], 404);
                }
            }

            $newImage = $this->createMergedImage($photo);

            // Resmi base64'e çevirelim
            ob_start();
            imagepng($newImage);
            $imageData = ob_get_clean();
            $base64Image = base64_encode($imageData);

            // Belleği temizleyelim
            imagedestroy($newImage);

            return response()->json([
                'success' => true,
                'message' => 'Photo merged successfully',
                'image' => 'data:image/png;base64,' . $base64Image,
                'photo_id' => $photo->id
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

    /**
     * Direkt olarak image dönen endpoint
     */
    public function showMergedPhoto(Request $request)
    {
        try {
            // ID parametresini kontrol edelim
            $photoId = $request->input('id');

            // ID varsa o fotoğrafı, yoksa son fotoğrafı alalım
            if ($photoId) {
                $photo = Photo::find($photoId);
                if (!$photo) {
                    abort(404, 'Photo not found');
                }
            } else {
                $photo = Photo::latest()->first();
                if (!$photo) {
                    abort(404, 'No photos found');
                }
            }

            $newImage = $this->createMergedImage($photo);

            // Header'ları ayarlayalım
            header('Content-Type: image/png');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            // Resmi direkt olarak çıktı olarak verelim
            imagepng($newImage);

            // Belleği temizleyelim
            imagedestroy($newImage);
            exit;

        } catch (\Exception $e) {
            Log::error('Photo merge error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            abort(500, $e->getMessage());
        }
    }
}
