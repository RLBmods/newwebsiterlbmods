<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadController extends Controller
{
    /**
     * Store an uploaded file.
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,svg,ico|max:2048',
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();
            $fileName = Str::uuid() . '.' . $extension;
            
            // Store the file in the public disk
            $path = $file->storeAs('uploads', $fileName, 'public');
            
            return response()->json([
                'url' => Storage::url($path),
                'name' => $file->getClientOriginalName(),
            ]);
        }

        return response()->json(['error' => 'No file uploaded'], 400);
    }
}
