<?php

use App\Http\Controllers\Authenticated\CRM\AttendanceController;
use App\Http\Controllers\Authenticated\CRM\CategoriesController;
use App\Http\Controllers\Authenticated\CRM\CustomerAddressController;
use App\Http\Controllers\Authenticated\CRM\CustomerController;
use App\Http\Controllers\Authenticated\CRM\DealersController;
use App\Http\Controllers\Authenticated\CRM\DealerBranchesController;
use App\Http\Controllers\Authenticated\CRM\ParentServicesController;
use App\Http\Controllers\Authenticated\CRM\ProductsController;
use App\Http\Controllers\Authenticated\CRM\ServicesController;
use App\Http\Controllers\Authenticated\CRM\StoreItemController;
use App\Http\Controllers\Authenticated\CRM\StoreItemInstanceController;
use App\Http\Controllers\Authenticated\CRM\OurBranchesController;
use App\Http\Controllers\Authenticated\CRM\AuthorizedBrandsController;
use App\Http\Controllers\Authenticated\CRM\MajorClientsController;
use App\Http\Controllers\Authenticated\CRM\StaffController;
use App\Http\Controllers\Authenticated\CRM\StaffContactController;
use App\Http\Controllers\Authenticated\CRM\StaffEducationController;
use App\Http\Controllers\Authenticated\CRM\StaffCertificationsController;
use App\Http\Controllers\Authenticated\CRM\StaffTrainingController;
use App\Http\Controllers\Authenticated\CRM\StaffSkillsController;
use App\Http\Controllers\Authenticated\CRM\StaffAssetsController;
use App\Http\Controllers\Authenticated\CRM\VehicleController;
use App\Http\Controllers\Authenticated\CRM\VehicleAssignmentController;
use App\Http\Controllers\Authenticated\CRM\VehicleUsageLogController;
use App\Http\Controllers\Authenticated\CRM\AuditLogController;
use App\Http\Controllers\Authenticated\CRM\SocialHandlersController;
use App\Http\Controllers\Authenticated\CRM\ApplicationLogController;
use App\Http\Controllers\Authenticated\CRM\TestBroadcastController;
use App\Http\Controllers\Authenticated\CRM\ServiceConcernController;
use App\Http\Controllers\Authenticated\CRM\ServiceSubConcernController;
use App\Http\Controllers\Authenticated\CRM\FileRequirementController;
use App\Http\Controllers\Authenticated\CRM\FileRequirementRuleController;
use App\Http\Controllers\Authenticated\CRM\WarrantyTypeController;
use App\Http\Controllers\Authenticated\CRM\WorkOrderController;
use App\Http\Controllers\Authenticated\CRM\WorkOrderFileController;
use App\Http\Controllers\Authenticated\CRM\NotificationController;
use App\Http\Controllers\Authenticated\CRM\DashboardController;
use App\Http\Controllers\Authenticated\FileTypeController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\RouteController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'crm'], function () {
    Route::group(['middleware' => ['auth:sanctum']], function () {
        Route::apiResource('/store-items', StoreItemController::class);
        Route::apiResource('/store-item-instances', StoreItemInstanceController::class);
        Route::apiResource('/products', ProductsController::class);
        Route::apiResource('/services', ServicesController::class);
        Route::apiResource('/categories', CategoriesController::class);
        Route::apiResource('/parent-services', ParentServicesController::class);
        Route::apiResource('/our-branches', OurBranchesController::class);
        Route::apiResource('/authorized-brands', AuthorizedBrandsController::class);
        Route::apiResource('/major-clients', MajorClientsController::class);
        Route::apiResource('/staff', StaffController::class);
        Route::get('/rawData/staff', [StaffController::class, 'staffRaw']);
        Route::apiResource('/roles', RoleController::class);
        Route::apiResource('/permissions', PermissionController::class);
        Route::post('/role-permissions/sync', [RolePermissionController::class, 'syncPermissions']);
        Route::get('/attendances/statistics', [AttendanceController::class, 'statistics']);
        Route::apiResource('/attendances', AttendanceController::class);
        Route::apiResource('/routes', RouteController::class);
        Route::apiResource('/customers', CustomerController::class);
        Route::get('/rawData/customers', [CustomerController::class, 'customersRaw']);
        Route::apiResource('/customer-address', CustomerAddressController::class);
        Route::get('/rawData/customer-address', [CustomerAddressController::class, 'addressesRaw']);
        Route::get('/rawData/categories', [CategoriesController::class, 'categoriesRaw']);
        Route::get('/rawData/services', [ServicesController::class, 'servicesRaw']);
        Route::get('/rawData/parent-services', [ParentServicesController::class, 'parentServicesRaw']);
        Route::get('/rawData/products', [ProductsController::class, 'productsRaw']);
        Route::get('/rawData/dealers', [DealersController::class, 'dealersRaw']);
        Route::get('/rawData/dealer-branches', [DealerBranchesController::class, 'dealerBranchesRaw']);
        Route::get('/rawData/parts', [\App\Http\Controllers\Authenticated\CRM\PartController::class, 'partsRaw']);
        Route::apiResource('/dealers', DealersController::class);
        Route::apiResource('/dealer-branches', DealerBranchesController::class);
        Route::apiResource('/social-handlers', SocialHandlersController::class);
        // Staff Management Routes
        Route::apiResource('/staff-contacts', StaffContactController::class);
        Route::apiResource('/staff-education', StaffEducationController::class);
        Route::apiResource('/staff-certifications', StaffCertificationsController::class);
        Route::apiResource('/staff-training', StaffTrainingController::class);
        Route::apiResource('/staff-skills', StaffSkillsController::class);
        Route::apiResource('/staff-assets', StaffAssetsController::class);
        Route::apiResource('/vehicles', VehicleController::class);
        Route::apiResource('/vehicle-assignments', VehicleAssignmentController::class);
        Route::apiResource('/vehicle-usage-logs', VehicleUsageLogController::class);
        Route::apiResource('/audit-logs', AuditLogController::class);

        // WhatsApp Routes
        Route::prefix('whatsapp')->group(function () {
            Route::get('/conversations', [\App\Http\Controllers\Authenticated\CRM\WhatsAppController::class, 'index']);
            Route::get('/conversations/{id}', [\App\Http\Controllers\Authenticated\CRM\WhatsAppController::class, 'show']);
            Route::post('/send-message', [\App\Http\Controllers\Authenticated\CRM\WhatsAppController::class, 'sendMessage']);
            Route::post('/send-media', [\App\Http\Controllers\Authenticated\CRM\WhatsAppController::class, 'sendMediaMessage']);
            Route::post('/send-template', [\App\Http\Controllers\Authenticated\CRM\WhatsAppController::class, 'sendTemplate']);
            Route::post('/mark-as-read', [\App\Http\Controllers\Authenticated\CRM\WhatsAppController::class, 'markAsRead']);
            Route::get('/templates', [\App\Http\Controllers\Authenticated\CRM\WhatsAppController::class, 'getTemplates']);
            Route::post('/templates/sync', [\App\Http\Controllers\Authenticated\CRM\WhatsAppController::class, 'syncTemplates']);
            Route::get('/contacts', [\App\Http\Controllers\Authenticated\CRM\WhatsAppController::class, 'getContacts']);
            Route::put('/contacts/{id}/opt-in', [\App\Http\Controllers\Authenticated\CRM\WhatsAppController::class, 'updateContactOptIn']);
            Route::put('/conversations/{id}/status', [\App\Http\Controllers\Authenticated\CRM\WhatsAppController::class, 'updateConversationStatus']);
            Route::put('/conversations/{id}/assign', [\App\Http\Controllers\Authenticated\CRM\WhatsAppController::class, 'assignConversation']);
        });

        // API Tokens Management
        Route::apiResource('/api-tokens', \App\Http\Controllers\Authenticated\CRM\ApiTokenController::class);
        Route::post('/api-tokens/{id}/activate', [\App\Http\Controllers\Authenticated\CRM\ApiTokenController::class, 'activate']);
        Route::post('/api-tokens/{id}/deactivate', [\App\Http\Controllers\Authenticated\CRM\ApiTokenController::class, 'deactivate']);

        // Business Phone Numbers Management
        Route::apiResource('/business-phone-numbers', \App\Http\Controllers\Authenticated\CRM\BusinessPhoneNumberController::class);
        Route::post('/business-phone-numbers/{id}/verify', [\App\Http\Controllers\Authenticated\CRM\BusinessPhoneNumberController::class, 'verify']);
        Route::post('/business-phone-numbers/{id}/set-default', [\App\Http\Controllers\Authenticated\CRM\BusinessPhoneNumberController::class, 'setDefault']);
        Route::post('/business-phone-numbers/{id}/activate', [\App\Http\Controllers\Authenticated\CRM\BusinessPhoneNumberController::class, 'activate']);
        Route::post('/business-phone-numbers/{id}/deactivate', [\App\Http\Controllers\Authenticated\CRM\BusinessPhoneNumberController::class, 'deactivate']);
        Route::post('/business-phone-numbers/{id}/suspend', [\App\Http\Controllers\Authenticated\CRM\BusinessPhoneNumberController::class, 'suspend']);

        // Application Logs Management
        Route::get('/logs', [ApplicationLogController::class, 'index']);
        Route::get('/logs/statistics', [ApplicationLogController::class, 'statistics']);
        Route::get('/logs/{id}', [ApplicationLogController::class, 'show']);

        // Test Broadcast (for development)
        Route::post('/test-broadcast', [TestBroadcastController::class, 'testMessageBroadcast']);

        // Notifications Management
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

        // Dashboard Statistics
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/admin', [DashboardController::class, 'adminDashboard']);
        Route::get('/dashboard/analytics', [DashboardController::class, 'analytics']);

        // Work Orders Management
        Route::apiResource('/work-orders', WorkOrderController::class);
        Route::get('/work-orders/{id}/history', [WorkOrderController::class, 'history']);
        Route::post('/work-orders/{id}/lock', [WorkOrderController::class, 'lock']);
        Route::post('/work-orders/{id}/schedule', [WorkOrderController::class, 'schedule']);
        Route::post('/work-orders/{id}/assign', [WorkOrderController::class, 'assign']);
        Route::post('/work-orders/{id}/cancel', [WorkOrderController::class, 'cancel']);
        Route::post('/work-orders/{id}/duplicate', [WorkOrderController::class, 'duplicate']);
        Route::post('/work-orders/{id}/reopen', [WorkOrderController::class, 'reopen']);
        Route::post('/work-orders/{id}/send-reminder', [WorkOrderController::class, 'sendReminder']);
        Route::post('/work-orders/{id}/accept', [WorkOrderController::class, 'acceptWorkOrder']);
        Route::post('/work-orders/{id}/start-service', [WorkOrderController::class, 'startService']);
        Route::post('/work-orders/{id}/start-work', [WorkOrderController::class, 'startWork']);
        Route::post('/work-orders/{id}/complete-service', [WorkOrderController::class, 'completeService']);
        Route::post('/work-orders/{id}/mark-part-in-demand', [WorkOrderController::class, 'markAsPartInDemand']);
        Route::post('/work-orders/{id}/complete-from-part-demand', [WorkOrderController::class, 'completeFromPartDemand']);

        // Parts Management
        Route::apiResource('/parts', \App\Http\Controllers\Authenticated\CRM\PartController::class);

        // Work Order Files Management (nested routes)
        Route::prefix('work-orders/{workOrderId}')->group(function () {
            Route::get('/files', [WorkOrderFileController::class, 'index']);
            Route::post('/files', [WorkOrderFileController::class, 'store']);
            Route::get('/files/download-all', [WorkOrderFileController::class, 'downloadAll']);
            Route::get('/files/{fileId}/download', [WorkOrderFileController::class, 'download']);
            Route::patch('/files/{fileId}', [WorkOrderFileController::class, 'update']);
            Route::delete('/files/{fileId}', [WorkOrderFileController::class, 'destroy']);

            // Customer Feedbacks
            Route::get('/feedbacks', [\App\Http\Controllers\Authenticated\CRM\CustomerFeedbackController::class, 'index']);
            Route::post('/feedbacks', [\App\Http\Controllers\Authenticated\CRM\CustomerFeedbackController::class, 'store']);
            Route::put('/feedbacks/{id}', [\App\Http\Controllers\Authenticated\CRM\CustomerFeedbackController::class, 'update']);
            Route::delete('/feedbacks/{id}', [\App\Http\Controllers\Authenticated\CRM\CustomerFeedbackController::class, 'destroy']);

            // Work Order Parts
            Route::get('/parts', [\App\Http\Controllers\Authenticated\CRM\WorkOrderPartController::class, 'index']);
            Route::post('/parts', [\App\Http\Controllers\Authenticated\CRM\WorkOrderPartController::class, 'store']);
            Route::patch('/parts/bulk-status', [\App\Http\Controllers\Authenticated\CRM\WorkOrderPartController::class, 'bulkUpdateStatus']);
            Route::patch('/parts/{workOrderPartId}', [\App\Http\Controllers\Authenticated\CRM\WorkOrderPartController::class, 'update']);
            Route::delete('/parts/{workOrderPartId}', [\App\Http\Controllers\Authenticated\CRM\WorkOrderPartController::class, 'destroy']);
        });

        Route::apiResource('/work-order-statuses', \App\Http\Controllers\Authenticated\CRM\WorkOrderStatusController::class);

        // File Types Management
        Route::apiResource('/file-types', FileTypeController::class);
        Route::post('/file-types/{id}/toggle-status', [FileTypeController::class, 'toggleStatus']);
        Route::post('/file-types/{id}/restore', [FileTypeController::class, 'restore']);
        Route::post('/file-types/{id}/validate-file', [FileTypeController::class, 'validateFile']);

        // Service Concerns & Sub-Concerns
        Route::apiResource('/service-concerns', ServiceConcernController::class);
        Route::get('/parent-services/{id}/concerns', [ServiceConcernController::class, 'getByParentService']);

        Route::apiResource('/service-sub-concerns', ServiceSubConcernController::class);
        Route::get('/service-concerns/{id}/sub-concerns', [ServiceSubConcernController::class, 'getByConcern']);

        // File Requirements (Dynamic API for fetching requirements)
        Route::get('/file-requirements', [FileRequirementController::class, 'getRequirements']);
        Route::post('/file-requirements/validate', [FileRequirementController::class, 'validateFiles']);

        // File Requirement Rules (CRUD Admin)
        Route::apiResource('file-requirement-rules', FileRequirementRuleController::class);

        // Warranty Types
        Route::apiResource('warranty-types', WarrantyTypeController::class);
        Route::get('/warranty-types-raw', [WarrantyTypeController::class, 'warrantyTypesRaw']);

        // Raw Data Endpoints for SearchSelect Components
        Route::get('/parent-services-raw', [ParentServicesController::class, 'parentServicesRaw']);
        Route::get('/service-concerns-raw', [ServiceConcernController::class, 'serviceConcernsRaw']);
        Route::get('/service-sub-concerns-raw', [ServiceSubConcernController::class, 'serviceSubConcernsRaw']);
        Route::get('/authorized-brands-raw', [AuthorizedBrandsController::class, 'authorizedBrandsRaw']);
        Route::get('/categories-raw', [CategoriesController::class, 'categoriesRaw']);
        Route::get('/file-types-raw', [FileTypeController::class, 'fileTypesRaw']);
    });
});
