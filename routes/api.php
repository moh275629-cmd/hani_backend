<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CloudinarySignatureController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LoyaltyCardController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\QRController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\GlobalAdminController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\OfferRatingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ActivationController;
use App\Http\Controllers\WilayaController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\RequiredDocumentsController;
use App\Http\Controllers\StoreImageController;
use App\Http\Controllers\OfferImageController;
use App\Http\Controllers\DocumentController;


// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/store/register', [App\Http\Controllers\StoreRegistrationController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/send-email-otp', [AuthController::class, 'sendEmailOtp']);
Route::post('/send-phone-otp', [AuthController::class, 'sendPhoneOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);

// Password reset routes
Route::post('/password/reset-request', [AuthController::class, 'requestPasswordReset']);
Route::post('/password/reset', [AuthController::class, 'resetPassword']);

// Public Wilaya routes
Route::get('/wilayas', function () {
    return response()->json([
        'message' => 'Wilayas retrieved successfully',
        'data' => \App\Models\Wilaya::getActiveWilayas()
    ]);
});

Route::get('/wilayas/search', function (Request $request) {
    $query = $request->get('q', '');
    $wilayas = \App\Models\Wilaya::where(function ($q) use ($query) {
        $q->where('name_en', 'like', "%{$query}%")
          ->orWhere('name_fr', 'like', "%{$query}%")
          ->orWhere('name_ar', 'like', "%{$query}%")
          ->orWhere('code', 'like', "%{$query}%");
    })->where('is_active', true)->get();
    
    return response()->json([
        'message' => 'Wilayas search completed',
        'data' => $wilayas
    ]);
});

// Public City routes
Route::get('/cities', function () {
    return response()->json([
        'message' => 'Cities retrieved successfully',
        'data' => \App\Models\City::getActiveCities()
    ]);
});

Route::get('/cities/search', function (Request $request) {
    $query = $request->get('q', '');
    $wilayaCode = $request->get('wilaya_code');
    
    $citiesQuery = \App\Models\City::where(function ($q) use ($query) {
        $q->where('name_en', 'like', "%{$query}%")
          ->orWhere('name_fr', 'like', "%{$query}%")
          ->orWhere('name_ar', 'like', "%{$query}%")
          ->orWhere('code', 'like', "%{$query}%");
    })->where('is_active', true);
    
    if ($wilayaCode) {
        $citiesQuery->where('wilaya_code', $wilayaCode);
    }
    
    $cities = $citiesQuery->with('wilaya')->orderBy('name_en')->limit(20)->get();
    
    return response()->json([
        'message' => 'Cities search completed',
        'data' => $cities
    ]);
});

Route::get('/cities/wilaya/{wilayaCode}', function ($wilayaCode) {
    $cities = \App\Models\City::getByWilaya($wilayaCode);
    
    return response()->json([
        'message' => 'Cities retrieved successfully',
        'data' => $cities
    ]);
});

// Public Business Types route
Route::get('/business-types', function () {
    return response()->json([
        'message' => 'Business types retrieved successfully',
        'data' => \App\Services\BusinessTypeService::getBusinessTypesForDropdown()
    ]);
});

// Required Documents routes (public for store registration)
Route::get('/required-documents/store', function () {
    $documents = \App\Models\RequiredDocuments::getForUserCategory('store');
    return response()->json([
        'success' => true,
        'data' => $documents
    ]);
});

// Test route to verify routing is working
Route::get('/test', function () {
    return response()->json(['message' => 'API is working']);
});

// Test database connection
Route::get('/test-db', function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $userCount = \App\Models\User::count();
        return response()->json([
            'message' => 'Database connection successful',
            'user_count' => $userCount,
            'database' => \Illuminate\Support\Facades\DB::connection()->getDatabaseName()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Database connection failed',
            'error' => $e->getMessage()
        ], 500);
    }
});

// QR Code scanning (public for store scanning)
Route::post('/qr/scan', [QRController::class, 'scan']);
Route::get('/qr/{code}', [QRController::class, 'show']);

// Image serving routes (public)
Route::get('/images/store/{storeId}/logo', [ImageController::class, 'storeLogo']);
Route::get('/images/store/{storeId}/banner', [ImageController::class, 'storeBanner']);
Route::get('/images/offer/{offerId}', [ImageController::class, 'offerImage']);
Route::get('/images/user/{userId}/profile', [ImageController::class, 'userProfileImage']);
Route::get('/images/temp/{tempId}', [ImageController::class, 'serveTempImage']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // (removed duplicate Global Admin admins management routes; see the dedicated global-admin group below)
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/profile/password', [AuthController::class, 'changePassword']);
    Route::post('/profile/delete', [AuthController::class, 'deleteAccount']);
    
    // ID Verification
    Route::post('/id-verification', [AuthController::class, 'submitIdVerification']);
    Route::get('/id-verification/status', [AuthController::class, 'getIdVerificationStatus']);
    
    // Loyalty Card
    Route::get('/loyalty-card', [LoyaltyCardController::class, 'show']);
    Route::post('/loyalty-card/activate', [LoyaltyCardController::class, 'activate']);
    Route::get('/loyalty-card/transactions', [LoyaltyCardController::class, 'transactions']);
    Route::post('/loyalty-card/regenerate-qr', [LoyaltyCardController::class, 'regenerateQRCode']);
    
    // Offers
    Route::get('/offers', [OfferController::class, 'index']);
    Route::get('/offers/{offer}', [OfferController::class, 'show']);
    Route::post('/offers/{offer}/redeem', [OfferController::class, 'redeem']);
    Route::get('/offers/redeemed', [OfferController::class, 'redeemed']);
    
    // Purchases
    Route::get('/purchases', [PurchaseController::class, 'index']);
    Route::get('/purchases/{purchase}', [PurchaseController::class, 'show']);
    Route::post('/purchases', [PurchaseController::class, 'store']);
    Route::post('/purchases/{purchase}/refund', [PurchaseController::class, 'refund']);
    Route::delete('/purchases/{purchase}', [PurchaseController::class, 'destroy']);
    
    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/{notification}', [NotificationController::class, 'show']);
    Route::put('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::get('/notifications/unread/count', [NotificationController::class, 'unreadCount']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);
    Route::get('/notifications/statistics', [NotificationController::class, 'statistics']);
    
    // Stores
    Route::get('/stores', [StoreController::class, 'index']);
    Route::get('/stores/me/offers', [StoreController::class, 'myOffers']);
    Route::post('/stores/me/offers', [StoreController::class, 'createMyOffer']);
    Route::put('/stores/me/offers/{offerId}', [StoreController::class, 'updateMyOffer']);
    Route::delete('/stores/me/offers/{offerId}', [StoreController::class, 'deleteMyOffer']);
    Route::put('/stores/location', [StoreController::class, 'updateLocation']);
    Route::get('/stores/nearby', [StoreController::class, 'nearby']);
    Route::get('/stores/categories', [StoreController::class, 'categories']);
    Route::get('/stores/statistics', [StoreController::class, 'statistics']);
    Route::get('/stores/search', [StoreController::class, 'search']);
    Route::get('/stores/mt', [StoreController::class, 'StoreAuthed']);
    Route::post('/stores/{storeId}/reject', [StoreController::class, 'reject']);
    Route::get('/stores/{storeId}', [StoreController::class, 'show'])->where('storeId', '[0-9]+');
    Route::get('/stores/{store}/transactions', [StoreController::class, 'transactions']);
    
    // Store Edit Requests
    Route::post('/store/edit-request', [App\Http\Controllers\EditStoreController::class, 'submit']);
    Route::get('/store/edit-requests', [App\Http\Controllers\EditStoreController::class, 'myRequests']);
    Route::get('/store/edit-requests/{id}', [App\Http\Controllers\EditStoreController::class, 'show']);
    Route::delete('/store/edit-requests/{id}', [App\Http\Controllers\EditStoreController::class, 'cancel']);
    
    // Ratings
    Route::post('/rate/user/{id}', [RatingController::class, 'rateUser']);
    Route::get('/ratings/user/{id}', [RatingController::class, 'getUserRatings']);
    
    // Reports
    Route::post('/reports', [ReportController::class, 'store']);
    Route::get('/reports', [ReportController::class, 'getUserReports']);
    
    // Offer Ratings
    Route::post('/rate/offer/{id}', [OfferRatingController::class, 'rateOffer']);
    Route::get('/ratings/offer/{id}', [OfferRatingController::class, 'getOfferRatings']);
    Route::get('/ratings/offer/{id}/user', [OfferRatingController::class, 'getUserOfferRating']);

    // Documents (authenticated user)
    Route::get('/documents/user/{userId}', [DocumentController::class, 'listByUser']);
    Route::delete('/documents/user/{userId}/{documentId}', [DocumentController::class, 'deleteByUser']);
    
    // Image upload routes
    Route::post('/images/store/{storeId}/logo', [ImageController::class, 'uploadStoreLogo']);
    Route::post('/images/store/{storeId}/banner', [ImageController::class, 'uploadStoreBanner']);
    
    // Store image management routes
    Route::post('/store/{storeId}/images', [StoreImageController::class, 'uploadImage']);
    Route::get('/store/{storeId}/images', [StoreImageController::class, 'getImages']);
    Route::delete('/store/{storeId}/images', [StoreImageController::class, 'deleteImage']);
    
    // Offer media management routes
    Route::post('/offer/{offerId}/media', [OfferImageController::class, 'uploadMedia']);
    Route::get('/offer/{offerId}/media', [OfferImageController::class, 'getMedia']);
    Route::delete('/offer/{offerId}/media', [OfferImageController::class, 'deleteMedia']);
    Route::post('/images/store-logo/temp', [ImageController::class, 'uploadTempStoreLogo']); // For store owners
    Route::post('/images/store/{storeId}/logo/move-temp', [ImageController::class, 'moveTempStoreLogo']);
    Route::post('/images/offer/temp', [ImageController::class, 'uploadTempOfferImage']); // Moved here to fix route conflict
    Route::post('/images/offer/{offerId}', [ImageController::class, 'uploadOfferImage']);
    Route::post('/images/offer/{offerId}/move-temp', [ImageController::class, 'moveTempOfferImage']);
    Route::post('/images/profile/temp', [ImageController::class, 'uploadTempProfileImage']);
    Route::post('/images/profile/move-temp', [ImageController::class, 'moveTempProfileImage']);
    Route::post('/images/profile/upload', [ImageController::class, 'uploadProfileImage']); // Direct upload for client users
    Route::post('/images/test-upload', [ImageController::class, 'testUpload']); // Test route for debugging
    
    // QR Code generation
    Route::get('/qr/generate', [QRController::class, 'generate']);
    Route::post('/qr/validate', [QRController::class, 'validate']);
    
    // Purchase statistics
    Route::get('/purchases/statistics', [PurchaseController::class, 'statistics']);
    
    // Admin routes
    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/users/{user}', [AdminController::class, 'user']);
        Route::put('/users/{user}', [AdminController::class, 'updateUserProfile']);
        Route::put('/users/{user}/status', [AdminController::class, 'updateUserStatus']);
        Route::get('/stores', [AdminController::class, 'stores']);
        Route::post('/stores', [AdminController::class, 'createStore']);
        Route::put('/stores/{store}', [AdminController::class, 'updateStore']);
        Route::delete('/stores/{store}', [AdminController::class, 'deleteStore']);
        Route::put('/stores/{store}/status', [AdminController::class, 'updateStoreStatus']);
        Route::put('/stores/{store}/approval', [AdminController::class, 'toggleStoreApproval']);
        Route::get('/offers', [AdminController::class, 'offers']);
        Route::post('/offers', [AdminController::class, 'createOffer']);
        Route::put('/offers/{offer}', [AdminController::class, 'updateOffer']);
        Route::delete('/offers/{offer}', [AdminController::class, 'deleteOffer']);
        Route::get('/analytics', [AdminController::class, 'analytics']);
        
        // Reports Management
        Route::get('/reports', [AdminController::class, 'getReports']);
        Route::put('/reports/{id}/status', [ReportController::class, 'updateStatus']);
        
        // Expired Accounts Management
        Route::get('/expired-accounts', [AdminController::class, 'getExpiredAccounts']);
        Route::get('/expiring-soon', [AdminController::class, 'getExpiringSoon']);
        Route::post('/expired-accounts/{userId}/reactivate', [ActivationController::class, 'reactivateAccount']);
        Route::post('/expired-accounts/{userId}/extend', [ActivationController::class, 'extendActivation']);
        Route::post('/expired-accounts/{userId}/deactivate', [ActivationController::class, 'deactivateAccount']);
        
        // Client Approval
        Route::get('/pending-client-approvals', [AdminController::class, 'getPendingClientApprovals']);
        Route::post('/clients/{userId}/approve', [AdminController::class, 'approveClient']);
        
        // Admin Profile Management
        Route::put('/admin/profile/{adminId}', [AdminController::class, 'updateProfile']);
        
        // Business Type Management
        Route::get('/business-types', [App\Http\Controllers\BusinessTypeController::class, 'index']);
        Route::get('/business-types/dropdown', [App\Http\Controllers\BusinessTypeController::class, 'getForDropdown']);
        Route::post('/business-types', [App\Http\Controllers\BusinessTypeController::class, 'store']);
        Route::put('/business-types/{id}', [App\Http\Controllers\BusinessTypeController::class, 'update']);
        Route::delete('/business-types/{id}', [App\Http\Controllers\BusinessTypeController::class, 'destroy']);
        Route::post('/stores/{storeId}/approve-custom-business-type', [App\Http\Controllers\BusinessTypeController::class, 'approveCustomType']);
        
        // Store Edit Request Management
        Route::get('/store-edit-requests', [App\Http\Controllers\EditStoreController::class, 'adminIndex']);
        Route::get('/store-edit-requests/{id}', [App\Http\Controllers\EditStoreController::class, 'adminShow']);
        Route::post('/store-edit-requests/{id}/approve', [App\Http\Controllers\EditStoreController::class, 'approve']);
        Route::post('/store-edit-requests/{id}/reject', [App\Http\Controllers\EditStoreController::class, 'reject']);
        
        // Admin image upload routes
        Route::post('/images/store/temp/logo', [ImageController::class, 'uploadTempStoreLogo']);
        Route::post('/images/store/temp/banner', [ImageController::class, 'uploadTempStoreBanner']);
    });

    // Global Admin routes
    Route::prefix('global-admin')->middleware('global.admin')->group(function () {
        // Admins management (handled by GlobalAdminController)
        Route::get('/admins', [GlobalAdminController::class, 'admins']);
        Route::post('/admins', [GlobalAdminController::class, 'createAdmin']);
        Route::get('/admins/{admin}', [GlobalAdminController::class, 'getAdmin']);
        Route::put('/admins/{admin}', [GlobalAdminController::class, 'updateAdmin']);
        Route::delete('/admins/{admin}', [GlobalAdminController::class, 'deleteAdmin']);
        
        Route::get('/wilayas/with-admins', [WilayaController::class, 'withAdmins']);
        
        Route::get('/terms-and-conditions', [GlobalAdminController::class, 'termsAndConditions']);
        Route::post('/terms-and-conditions', [GlobalAdminController::class, 'createTermsAndConditions']);
        Route::put('/terms-and-conditions/{terms}', [GlobalAdminController::class, 'updateTermsAndConditions']);
        Route::delete('/terms-and-conditions/{terms}', [GlobalAdminController::class, 'deleteTermsAndConditions']);
        
        Route::get('/required-documents', [GlobalAdminController::class, 'requiredDocuments']);
        Route::post('/required-documents', [GlobalAdminController::class, 'createRequiredDocument']);
        Route::put('/required-documents/{document}', [GlobalAdminController::class, 'updateRequiredDocument']);
        Route::delete('/required-documents/{document}', [GlobalAdminController::class, 'deleteRequiredDocument']);
        
        // Wilaya Management
        Route::prefix('wilayas')->group(function () {
            Route::get('/', [WilayaController::class, 'index']);
            Route::post('/', [WilayaController::class, 'store']);
            Route::get('/without-admin', [WilayaController::class, 'withoutAdmin']);
            Route::get('/{id}', [WilayaController::class, 'show']);
            Route::put('/{id}', [WilayaController::class, 'update']);
            Route::delete('/{id}', [WilayaController::class, 'destroy']);
            Route::post('/{id}/assign-admin', [WilayaController::class, 'assignAdmin']);
            Route::delete('/{id}/remove-admin', [WilayaController::class, 'removeAdmin']);
            Route::get('/{id}/statistics', [WilayaController::class, 'statistics']);
        });
        
        // Documents management (Admin)
        Route::prefix('documents')->group(function () {
            Route::get('/user/{userId}', [App\Http\Controllers\DocumentController::class, 'listByUser']);
        });

        // City Management
        Route::prefix('cities')->group(function () {
            Route::get('/', [CityController::class, 'index']);
            Route::post('/', [CityController::class, 'store']);
            Route::get('/wilaya/{wilayaCode}', [CityController::class, 'getByWilaya']);
            Route::get('/search', [CityController::class, 'search']);
            Route::get('/{id}', [CityController::class, 'show']);
            Route::put('/{id}', [CityController::class, 'update']);
            Route::delete('/{id}', [CityController::class, 'destroy']);
        });
    });
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Store registration specific routes
Route::get('/store/terms', [App\Http\Controllers\StoreRegistrationController::class, 'getTermsAndConditions']);
Route::get('/store/required-documents', [App\Http\Controllers\StoreRegistrationController::class, 'getRequiredDocuments']);
Route::post('/store/{userId}/documents', [App\Http\Controllers\DocumentController::class, 'upload']);
Route::post('/store/documents/login', [App\Http\Controllers\DocumentController::class, 'uploadByCredentials']);
Route::post('/store/documents/list-by-credentials', [DocumentController::class, 'listByCredentials']);
Route::delete('/store/documents/delete-by-credentials', [DocumentController::class, 'deleteByCredentials']);
Route::post('/store/documents/delete-by-credentials', [DocumentController::class, 'deleteByCredentials']);

Route::get('/php-config', function () {
    return response()->json([
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_file_uploads' => ini_get('max_file_uploads'),
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit'),
    ]);
});

Route::get('/test-offer-image', function () {
    try {
        // Check if offers table exists and has image_blob column
        $dbCheck = \DB::select("DESCRIBE offers");
        $imageBlobColumn = collect($dbCheck)->firstWhere('Field', 'image_blob');
        
        // Try to find an offer
        $offer = \App\Models\Offer::first();
        
        return response()->json([
            'success' => true,
            'offers_table_exists' => true,
            'image_blob_column_exists' => $imageBlobColumn ? true : false,
            'image_blob_column_info' => $imageBlobColumn,
            'total_offers' => \App\Models\Offer::count(),
            'sample_offer' => $offer ? [
                'id' => $offer->id,
                'title' => $offer->title,
                'has_image_blob' => $offer->hasImageBlob(),
                'image_blob_size' => $offer->image_blob ? strlen($offer->image_blob) : 0,
            ] : null,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'stack_trace' => $e->getTraceAsString(),
        ], 500);
    }
});

Route::post('/test-png-upload', function (Request $request) {
    try {
        \Log::info('test-png-upload called');
        \Log::info('Request has files: ' . $request->hasFile('image'));
        \Log::info('Request files count: ' . count($request->allFiles()));
        \Log::info('Request content type: ' . $request->header('Content-Type'));
        \Log::info('Request content length: ' . $request->header('Content-Length'));
        
        // Debug all request data
        \Log::info('All request data: ' . json_encode($request->all()));
        \Log::info('All files: ' . json_encode($request->allFiles()));
        \Log::info('Request input: ' . json_encode($request->input()));
        
        // Debug PHP configuration
        \Log::info('PHP upload_max_filesize: ' . ini_get('upload_max_filesize'));
        \Log::info('PHP post_max_size: ' . ini_get('post_max_size'));
        \Log::info('PHP max_file_uploads: ' . ini_get('max_file_uploads'));
        
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            \Log::info('File details: ' . json_encode([
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'extension' => $file->getClientOriginalExtension(),
                'is_valid' => $file->isValid(),
                'error' => $file->getError(),
            ]));
            
            return response()->json([
                'success' => true,
                'message' => 'PNG file upload test successful',
                'file_info' => [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'extension' => $file->getClientOriginalExtension(),
                    'is_valid' => $file->isValid(),
                    'error' => $file->getError(),
                ]
            ]);
        } else {
            // Try alternative methods to detect the file
            \Log::info('Trying alternative file detection methods...');
            
            // Check if there are any files at all
            $allFiles = $request->allFiles();
            \Log::info('All files in request: ' . json_encode($allFiles));
            
            // Check if the file might be in a different field name
            foreach ($allFiles as $fieldName => $file) {
                \Log::info("Found file in field '$fieldName': " . json_encode([
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ]));
            }
            
            return response()->json([
                'success' => false,
                'message' => 'No file uploaded',
                'request_info' => [
                    'has_files' => $request->hasFile('image'),
                    'files_count' => count($request->allFiles()),
                    'content_type' => $request->header('Content-Type'),
                    'content_length' => $request->header('Content-Length'),
                    'all_files' => array_keys($allFiles),
                    'request_data' => $request->all(),
                ]
            ], 400);
        }
    } catch (\Exception $e) {
        \Log::error('Test PNG upload error: ' . $e->getMessage());
        \Log::error('Stack trace: ' . $e->getTraceAsString());
        return response()->json([
            'success' => false,
            'message' => 'Test PNG upload failed: ' . $e->getMessage(),
            'error' => $e->getMessage()
        ], 500);
    }
});

// Required Documents routes
Route::middleware('auth:sanctum')->group(function () {
    // Admin routes for managing required documents
    Route::middleware('admin')->group(function () {
        Route::get('/admin/required-documents', [RequiredDocumentsController::class, 'index']);
        Route::post('/admin/required-documents', [RequiredDocumentsController::class, 'store']);
        Route::put('/admin/required-documents/{id}', [RequiredDocumentsController::class, 'update']);
        Route::delete('/admin/required-documents/{id}', [RequiredDocumentsController::class, 'destroy']);
        Route::get('/admin/required-documents/types', [RequiredDocumentsController::class, 'getDocumentTypes']);
        Route::get('/admin/required-documents/categories', [RequiredDocumentsController::class, 'getUserCategories']);
        Route::get('/admin/required-documents/file-types', [RequiredDocumentsController::class, 'getFileTypes']);
    });
    
    // Public routes for getting documents by category
    Route::get('/required-documents/category/{category}', [RequiredDocumentsController::class, 'getForCategory']);
    Route::get('/required-documents/required/{category}', [RequiredDocumentsController::class, 'getRequiredForCategory']);
});

// Test route for Cloudinary configuration
Route::get('/test-cloudinary-config', function () {
    try {
        return response()->json([
            'cloudinary_config' => [
                'cloud_name' => config('cloudinary.cloud_name') ?? config('services.cloudinary.cloud_name'),
                'api_key' => config('cloudinary.api_key') ?? config('services.cloudinary.api_key'),
                'api_secret' => config('cloudinary.api_secret') ? 'Set' : 'Not Set',
                'secure' => config('cloudinary.secure') ?? config('services.cloudinary.secure'),
            ],
            'env_vars' => [
                'CLOUDINARY_CLOUD_NAME' => env('CLOUDINARY_CLOUD_NAME'),
                'CLOUDINARY_API_KEY' => env('CLOUDINARY_API_KEY'),
                'CLOUDINARY_API_SECRET' => env('CLOUDINARY_API_SECRET') ? 'Set' : 'Not Set',
                'CLOUDINARY_SECURE' => env('CLOUDINARY_SECURE'),
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// Test route for Cloudinary upload
Route::post('/test-cloudinary-upload', function (Request $request) {
    try {
        $cloudinaryService = app(\App\Services\CloudinaryService::class);
        
        if (!$request->hasFile('test_file')) {
            return response()->json(['error' => 'No file provided'], 400);
        }
        
        $file = $request->file('test_file');
        $result = $cloudinaryService->uploadFile($file, 'test');
        
        return response()->json($result);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// Cloudinary signature endpoint for client-side direct uploads
Route::post('/cloudinary/signature', [CloudinarySignatureController::class, 'sign']);

