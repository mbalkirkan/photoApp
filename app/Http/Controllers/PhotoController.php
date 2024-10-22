<?php

namespace App\Http\Controllers;

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
            $backgroundPath = public_path('background.png'); // background.jpg'yi public klasörüne koymalısınız

            // Son yüklenen fotoğrafın yolu
            $uploadedPhotoPath = storage_path('app/' . $lastPhoto->path);

            // Arka plan resmini yükleyelim
            $background = imagecreatefromjpeg($backgroundPath);

            // Yüklenen fotoğrafı yükleyelim (uzantıya göre)
            $extension = pathinfo($uploadedPhotoPath, PATHINFO_EXTENSION);
            $uploadedPhoto = null;

            switch(strtolower($extension)) {
                case 'jpg':
                case 'jpeg':
                    $uploadedPhoto = imagecreatefromjpeg($uploadedPhotoPath);
                    break;
                case 'png':
                    $uploadedPhoto = imagecreatefrompng($uploadedPhotoPath);
                    break;
                default:
                    throw new \Exception('Unsupported image format');
            }

            // Resimlerin boyutlarını alalım
            $bgWidth = imagesx($background);
            $bgHeight = imagesy($background);
            $upWidth = imagesx($uploadedPhoto);
            $upHeight = imagesy($uploadedPhoto);

            // Yüklenen fotoğrafı %70 oranında küçültelim
            $newWidth = $upWidth * 0.7;
            $newHeight = $upHeight * 0.7;

            // Merkez koordinatlarını hesaplayalım
            $destX = ($bgWidth - $newWidth) / 2;
            $destY = ($bgHeight - $newHeight) / 2;

            // Resimleri birleştirelim
            imagecopyresampled(
                $background,
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
            $newFileName = 'merged_' . time() . '.jpg';
            $newFilePath = storage_path('app/public/photos/' . $newFileName);

            imagejpeg($background, $newFilePath, 90);

            // Belleği temizleyelim
            imagedestroy($background);
            imagedestroy($uploadedPhoto);

            return response()->json([
                'success' => true,
                'message' => 'Photo merged successfully',
                'url' => Storage::url('photos/' . $newFileName)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
