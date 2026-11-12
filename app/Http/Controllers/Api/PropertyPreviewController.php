<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PropertyPreviewController extends Controller
{
    /**
     * Generate or retrieve a preview token for a specific property.
     * This requires authentication.
     */
    public function generateToken($id)
    {
        $property = Property::findOrFail($id);

        if (empty($property->preview_token)) {
            $property->preview_token = Str::random(10);
            $property->save();
        }

        return response()->json([
            'success' => true,
            'preview_token' => $property->preview_token,
            'preview_url' => url('/preview/' . $property->preview_token),
        ]);
    }

    /**
     * Fetch a property for preview by its token.
     * This is a public endpoint.
     */
    public function getPreview($token)
    {
        $property = Property::with([
            'city',
            'community',
            'subCommunity',
            'buildingTower',
            'agent',
            'media',
            'videos',
        ])
        ->where('preview_token', $token)
        ->first();

        if (!$property) {
            return response()->json(['success' => false, 'message' => 'Preview not found or invalid token.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $property
        ]);
    }
}

