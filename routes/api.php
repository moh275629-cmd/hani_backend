<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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
        'data' => \App\Services\WilayaService::getAllWilayas()
    ]);
});

Route::get('/wilayas/search', function (Request $request) {
    $query = $request->get('q', '');
    $results = \App\Services\WilayaService::searchWilayas($query);
    
    return response()->json([
        'message' => 'Wilayas search completed',
        'data' => $results
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
    
    // Offer Ratings
    Route::post('/rate/offer/{id}', [OfferRatingController::class, 'rateOffer']);
    Route::get('/ratings/offer/{id}', [OfferRatingController::class, 'getOfferRatings']);
    Route::get('/ratings/offer/{id}/user', [OfferRatingController::class, 'getUserOfferRating']);
    
    // Image upload routes
    Route::post('/images/store/{storeId}/logo', [ImageController::class, 'uploadStoreLogo']);
    Route::post('/images/store/{storeId}/banner', [ImageController::class, 'uploadStoreBanner']);
    Route::post('/images/store-logo/temp', [ImageController::class, 'uploadTempStoreLogo']); // For store owners
    Route::post('/images/store/{storeId}/logo/move-temp', [ImageController::class, 'moveTempStoreLogo']);
    Route::post('/images/offer/temp', [ImageController::class, 'uploadTempOfferImage']); // Moved here to fix route conflict
    Route::post('/images/offer/{offerId}', [ImageController::class, 'uploadOfferImage']);
    Route::post('/images/offer/{offerId}/move-temp', [ImageController::class, 'moveTempOfferImage']);
    Route::post('/images/profile/temp', [ImageController::class, 'uploadTempProfileImage']);
    Route::post('/images/profile/move-temp', [ImageController::class, 'moveTempProfileImage']);
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
        Route::get('/admins', [GlobalAdminController::class, 'admins']);
        Route::post('/admins', [GlobalAdminController::class, 'createAdmin']);
        Route::put('/admins/{admin}', [GlobalAdminController::class, 'updateAdmin']);
        Route::delete('/admins/{admin}', [GlobalAdminController::class, 'deleteAdmin']);
        
        Route::get('/terms-and-conditions', [GlobalAdminController::class, 'termsAndConditions']);
        Route::post('/terms-and-conditions', [GlobalAdminController::class, 'createTermsAndConditions']);
        Route::put('/terms-and-conditions/{terms}', [GlobalAdminController::class, 'updateTermsAndConditions']);
        Route::delete('/terms-and-conditions/{terms}', [GlobalAdminController::class, 'deleteTermsAndConditions']);
        
        Route::get('/required-documents', [GlobalAdminController::class, 'requiredDocuments']);
        Route::post('/required-documents', [GlobalAdminController::class, 'createRequiredDocument']);
        Route::put('/required-documents/{document}', [GlobalAdminController::class, 'updateRequiredDocument']);
        Route::delete('/required-documents/{document}', [GlobalAdminController::class, 'deleteRequiredDocument']);
    });
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Store registration specific routes
Route::get('/store/terms', [App\Http\Controllers\StoreRegistrationController::class, 'getTermsAndConditions']);
Route::get('/store/required-documents', [App\Http\Controllers\StoreRegistrationController::class, 'getRequiredDocuments']);
