<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DownloadFileController extends Controller
{
    /**
     * List all files in the downloads directory.
     */
    public function list()
    {
        $files = Storage::disk('public')->files('downloads');

        $fileList = collect($files)->map(function ($file) {
            return [
                'name' => basename($file),
                'url'  => Storage::disk('public')->url($file),
                'size' => Storage::disk('public')->size($file),
                'path' => $file,
            ];
        })->values();

        return response()->json($fileList);
    }

    /**
     * Upload a new download file (exe / zip).
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                function ($attribute, $value, $fail) {
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (!in_array($ext, ['exe', 'zip'])) {
                        $fail('Only .exe and .zip files are allowed.');
                    }
                },
                'max:512000', // 500 MB
            ],
        ]);

        $file      = $request->file('file');
        $original  = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();

        // Keep original name (sanitised)
        $fileName  = preg_replace('/[^A-Za-z0-9._\-]/', '_', $original);

        $path = $file->storeAs('downloads', $fileName, 'public');

        return response()->json([
            'name' => $fileName,
            'url'  => Storage::disk('public')->url($path),
            'size' => $file->getSize(),
        ]);
    }
}
