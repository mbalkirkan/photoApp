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
            $photo->checked = $checked == 1;
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
}
