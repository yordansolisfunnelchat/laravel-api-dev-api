<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Resource;
use Illuminate\Support\Facades\Storage;

class MediaService
{
    public function getMediaUrl($type, $id)
    {
        switch ($type) {
            case 'image':
                return $this->getProductImageUrl($id);
            case 'document':
            case 'audio':
                return $this->getResourceFileUrl($id);
            default:
                return null;
        }
    }

    private function getProductImageUrl($imageId)
    {
        $parts = explode('_', $imageId);
        if (count($parts) !== 3 || $parts[0] !== 'product') {
            return null;
        }

        $productId = $parts[1];
        $imageIndex = $parts[2] - 1;

        $product = Product::find($productId);
        if (!$product || !isset($product->images[$imageIndex])) {
            return null;
        }

        return $product->images[$imageIndex];
    }

    private function getResourceFileUrl($resourceId)
    {
        $resource = Resource::find($resourceId);
        if (!$resource || !$resource->file) {
            return null;
        }

        return Storage::url($resource->file);
    }
}