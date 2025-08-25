# BLOB Image Storage Guide

This guide explains how to use the new BLOB image storage functionality for offers and stores.

## Overview

The system now stores images as BLOB data in the database instead of file paths. This provides better data integrity and easier backup/restore processes.

## Database Changes

### New Columns Added

**Stores Table:**
- `logo_blob` - BINARY column for store logo images
- `banner_blob` - BINARY column for store banner images

**Offers Table:**
- `image_blob` - BINARY column for offer images

### Migration

The migration `2025_01_20_000001_modify_image_fields_to_blob.php` adds these columns while preserving existing string-based image fields for backward compatibility.

## API Endpoints

### Image Serving (Public)

**Store Images:**
- `GET /api/images/store/{storeId}/logo` - Serve store logo
- `GET /api/images/store/{storeId}/banner` - Serve store banner

**Offer Images:**
- `GET /api/images/offer/{offerId}` - Serve offer image

### Image Upload (Protected)

**Store Images:**
- `POST /api/images/store/{storeId}/logo` - Upload store logo
- `POST /api/images/store/{storeId}/banner` - Upload store banner

**Offer Images:**
- `POST /api/images/offer/{offerId}` - Upload offer image

## Usage Examples

### Uploading Images

```php
// Upload store logo
$request->validate([
    'logo' => 'required|image|mimes:jpeg,png,jpg|max:2048'
]);

$store = Store::find($storeId);
$imageData = file_get_contents($request->file('logo')->getRealPath());
$store->setLogoBlob($imageData);

// Upload offer image
$request->validate([
    'image' => 'required|image|mimes:jpeg,png,jpg|max:2048'
]);

$offer = Offer::find($offerId);
$imageData = file_get_contents($request->file('image')->getRealPath());
$offer->setImageBlob($imageData);
```

### Serving Images

```php
// Serve store logo
public function storeLogo($storeId)
{
    $store = Store::findOrFail($storeId);
    
    if (!$store->hasLogoBlob()) {
        return response()->json(['error' => 'Logo not found'], 404);
    }

    $imageData = $store->getLogoBlob();
    
    return response($imageData)
        ->header('Content-Type', 'image/jpeg')
        ->header('Cache-Control', 'public, max-age=31536000');
}
```

## Model Methods

### Store Model

- `setLogoBlob($imageData)` - Store logo image data
- `setBannerBlob($imageData)` - Store banner image data
- `getLogoBlob()` - Retrieve logo image data
- `getBannerBlob()` - Retrieve banner image data
- `hasLogoBlob()` - Check if logo exists
- `hasBannerBlob()` - Check if banner exists

### Offer Model

- `setImageBlob($imageData)` - Store offer image data
- `getImageBlob()` - Retrieve offer image data
- `hasImageBlob()` - Check if image exists

## Flutter Integration

### Image URLs

The API automatically includes image URLs in responses:

```json
{
  "id": 1,
  "title": "Special Offer",
  "image_url": "http://localhost:8000/api/images/offer/1"
}
```

### Displaying Images

```dart
// In Flutter widgets
Image.network(
  offer.imageUrl!,
  fit: BoxFit.cover,
  errorBuilder: (context, error, stackTrace) {
    return Icon(Icons.image_not_supported);
  },
  loadingBuilder: (context, child, loadingProgress) {
    if (loadingProgress == null) return child;
    return CircularProgressIndicator();
  },
)
```

## Benefits

1. **Data Integrity** - Images are stored with the database, ensuring they're always available
2. **Backup/Restore** - Simple database backup includes all images
3. **No File System Dependencies** - No need to manage file paths or storage locations
4. **Atomic Operations** - Image uploads are part of database transactions
5. **Caching** - HTTP cache headers are automatically set for better performance

## Limitations

1. **Database Size** - Images increase database size significantly
2. **Memory Usage** - Loading large images requires more memory
3. **Network Transfer** - BLOB data is transferred as-is without compression

## Best Practices

1. **Image Compression** - Compress images before storing to reduce database size
2. **Size Limits** - Enforce reasonable file size limits (2MB for logos, 5MB for banners)
3. **Format Validation** - Only accept common image formats (JPEG, PNG)
4. **Caching** - Use appropriate cache headers for better performance
5. **Error Handling** - Always provide fallback UI for missing images

## Migration from File System

If migrating from file-based storage:

1. Run the migration to add BLOB columns
2. Convert existing image files to BLOB data
3. Update application code to use new endpoints
4. Test thoroughly before removing old file-based code
5. Clean up old image files after successful migration
