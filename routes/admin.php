<?php

use App\Http\Controllers\Admin\AttributeController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CatalogRuleController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerGroupController;
use App\Http\Controllers\Admin\DownloadableController;
use App\Http\Controllers\Admin\HeaderMenuController;
use App\Http\Controllers\Admin\HeaderSettingsController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\InventorySourceController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PaymentSettingsController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductLabelController;
use App\Http\Controllers\Admin\ProductVariantController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ShipmentController;
use App\Http\Controllers\Admin\ShippingMethodController;
use App\Http\Controllers\Admin\StoreConfigurationController;
use App\Http\Controllers\Admin\StoreController;
use App\Http\Controllers\Admin\StorefrontPageController;
use App\Http\Controllers\Admin\StoreScopeController;
use App\Http\Controllers\Admin\StoreShippingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WebsiteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [ReportController::class, 'dashboard'])
        ->middleware('permission:reports.view')
        ->name('dashboard');

    // Usuarios administrativos
    Route::middleware('permission:admin.users.view')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->middleware('permission:admin.users.create')->name('users.create');
        Route::post('users', [UserController::class, 'store'])->middleware('permission:admin.users.create')->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:admin.users.edit')->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->middleware('permission:admin.users.edit')->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:admin.users.delete')->name('users.destroy');
    });

    // Roles y permisos
    Route::middleware('permission:admin.roles.view')->group(function () {
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('roles/create', [RoleController::class, 'create'])->middleware('permission:admin.roles.create')->name('roles.create');
        Route::post('roles', [RoleController::class, 'store'])->middleware('permission:admin.roles.create')->name('roles.store');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:admin.roles.edit')->name('roles.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:admin.roles.edit')->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:admin.roles.delete')->name('roles.destroy');

        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
    });

    // Multisitio: websites, tiendas y configuración por scope.
    Route::middleware('permission:settings.stores')->group(function () {
        Route::resource('websites', WebsiteController::class)->except('show');
        Route::resource('stores', StoreController::class)->except('show');

        Route::get('configuration', [StoreConfigurationController::class, 'index'])->name('configuration.index');
        Route::put('configuration', [StoreConfigurationController::class, 'update'])->name('configuration.update');
    });

    // Configuración de pasarelas de pago (llaves y modo) por sitio.
    Route::middleware('permission:settings.payments')->group(function () {
        Route::get('payments', [PaymentSettingsController::class, 'index'])->name('payments.index');
        Route::put('payments', [PaymentSettingsController::class, 'update'])->name('payments.update');
    });

    // Catálogo: productos simples
    Route::middleware('permission:catalog.products.view')->group(function () {
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/create', [ProductController::class, 'create'])->middleware('permission:catalog.products.create')->name('products.create');
        Route::post('products', [ProductController::class, 'store'])->middleware('permission:catalog.products.create')->name('products.store');
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->middleware('permission:catalog.products.edit')->name('products.edit');
        Route::put('products/{product}', [ProductController::class, 'update'])->middleware('permission:catalog.products.edit')->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware('permission:catalog.products.delete')->name('products.destroy');

        // Variantes: vincular/desvincular productos simple existentes a un configurable.
        Route::post('products/{product}/variants/attach', [ProductVariantController::class, 'attach'])
            ->middleware('permission:catalog.products.edit')
            ->name('products.variants.attach');
        Route::delete('products/{product}/variants/{variant}/detach', [ProductVariantController::class, 'detach'])
            ->middleware('permission:catalog.products.edit')
            ->name('products.variants.detach');

        // Subida de archivos descargables (devuelve la ruta para el formulario de producto).
        Route::post('downloadable/upload', [DownloadableController::class, 'upload'])
            ->middleware('permission:catalog.products.create')
            ->name('downloadable.upload');
    });

    // Catálogo: categorías
    Route::middleware('permission:catalog.categories.view')->group(function () {
        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('categories/create', [CategoryController::class, 'create'])->middleware('permission:catalog.categories.create')->name('categories.create');
        Route::post('categories', [CategoryController::class, 'store'])->middleware('permission:catalog.categories.create')->name('categories.store');
        Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])->middleware('permission:catalog.categories.edit')->name('categories.edit');
        Route::put('categories/{category}', [CategoryController::class, 'update'])->middleware('permission:catalog.categories.edit')->name('categories.update');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:catalog.categories.delete')->name('categories.destroy');
    });

    // Catálogo: etiquetas (badges) de productos
    Route::middleware('permission:catalog.labels.view')->group(function () {
        Route::get('product-labels', [ProductLabelController::class, 'index'])->name('product-labels.index');
        Route::get('product-labels/create', [ProductLabelController::class, 'create'])->middleware('permission:catalog.labels.create')->name('product-labels.create');
        Route::post('product-labels', [ProductLabelController::class, 'store'])->middleware('permission:catalog.labels.create')->name('product-labels.store');
        Route::get('product-labels/{productLabel}/edit', [ProductLabelController::class, 'edit'])->middleware('permission:catalog.labels.edit')->name('product-labels.edit');
        Route::put('product-labels/{productLabel}', [ProductLabelController::class, 'update'])->middleware('permission:catalog.labels.edit')->name('product-labels.update');
        Route::delete('product-labels/{productLabel}', [ProductLabelController::class, 'destroy'])->middleware('permission:catalog.labels.delete')->name('product-labels.destroy');
    });

    // Clientes
    Route::middleware('permission:customers.view')->group(function () {
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/create', [CustomerController::class, 'create'])->middleware('permission:customers.create')->name('customers.create');
        Route::post('customers', [CustomerController::class, 'store'])->middleware('permission:customers.create')->name('customers.store');
        Route::get('customers/{customer}/edit', [CustomerController::class, 'edit'])->middleware('permission:customers.edit')->name('customers.edit');
        Route::put('customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:customers.edit')->name('customers.update');
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:customers.delete')->name('customers.destroy');
    });

    // Grupos de clientes (segmentación por website)
    Route::middleware('permission:customer_groups.view')->group(function () {
        Route::get('customer-groups', [CustomerGroupController::class, 'index'])->name('customer-groups.index');
        Route::get('customer-groups/create', [CustomerGroupController::class, 'create'])->middleware('permission:customer_groups.create')->name('customer-groups.create');
        Route::post('customer-groups', [CustomerGroupController::class, 'store'])->middleware('permission:customer_groups.create')->name('customer-groups.store');
        Route::get('customer-groups/{customerGroup}/edit', [CustomerGroupController::class, 'edit'])->middleware('permission:customer_groups.edit')->name('customer-groups.edit');
        Route::put('customer-groups/{customerGroup}', [CustomerGroupController::class, 'update'])->middleware('permission:customer_groups.edit')->name('customer-groups.update');
        Route::delete('customer-groups/{customerGroup}', [CustomerGroupController::class, 'destroy'])->middleware('permission:customer_groups.delete')->name('customer-groups.destroy');
    });

    // Catálogo: atributos
    Route::middleware('permission:catalog.attributes.view')->group(function () {
        Route::get('attributes', [AttributeController::class, 'index'])->name('attributes.index');
        Route::get('attributes/create', [AttributeController::class, 'create'])->middleware('permission:catalog.attributes.create')->name('attributes.create');
        Route::post('attributes', [AttributeController::class, 'store'])->middleware('permission:catalog.attributes.create')->name('attributes.store');
        Route::get('attributes/{attribute}/edit', [AttributeController::class, 'edit'])->middleware('permission:catalog.attributes.edit')->name('attributes.edit');
        Route::put('attributes/{attribute}', [AttributeController::class, 'update'])->middleware('permission:catalog.attributes.edit')->name('attributes.update');
        Route::delete('attributes/{attribute}', [AttributeController::class, 'destroy'])->middleware('permission:catalog.attributes.delete')->name('attributes.destroy');
    });

    // Inventario: almacenes (fuentes de stock)
    Route::middleware('permission:inventory.view')->group(function () {
        Route::get('inventory-sources', [InventorySourceController::class, 'index'])->name('inventory-sources.index');
        Route::get('inventory-sources/create', [InventorySourceController::class, 'create'])->middleware('permission:inventory.adjust')->name('inventory-sources.create');
        Route::post('inventory-sources', [InventorySourceController::class, 'store'])->middleware('permission:inventory.adjust')->name('inventory-sources.store');
        Route::get('inventory-sources/{inventorySource}/edit', [InventorySourceController::class, 'edit'])->middleware('permission:inventory.adjust')->name('inventory-sources.edit');
        Route::put('inventory-sources/{inventorySource}', [InventorySourceController::class, 'update'])->middleware('permission:inventory.adjust')->name('inventory-sources.update');
        Route::delete('inventory-sources/{inventorySource}', [InventorySourceController::class, 'destroy'])->middleware('permission:inventory.adjust')->name('inventory-sources.destroy');
    });

    // Inventario: stock por producto
    Route::middleware('permission:inventory.view')->group(function () {
        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('inventory/{product}', [InventoryController::class, 'edit'])->name('inventory.edit');
        Route::put('inventory/{product}', [InventoryController::class, 'update'])->middleware('permission:inventory.adjust')->name('inventory.update');
    });

    // Reportes de ventas (compatibilidad; la vista principal vive en Dashboard).
    Route::middleware('permission:reports.view')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    });

    // Promociones: reglas de carrito y cupones
    Route::middleware('permission:promotions.view')->group(function () {
        Route::get('promotions', [PromotionController::class, 'index'])->name('promotions.index');
        Route::get('promotions/create', [PromotionController::class, 'create'])->middleware('permission:promotions.create')->name('promotions.create');
        Route::post('promotions', [PromotionController::class, 'store'])->middleware('permission:promotions.create')->name('promotions.store');
        Route::get('promotions/{cartPriceRule}/edit', [PromotionController::class, 'edit'])->middleware('permission:promotions.edit')->name('promotions.edit');
        Route::put('promotions/{cartPriceRule}', [PromotionController::class, 'update'])->middleware('permission:promotions.edit')->name('promotions.update');
        Route::delete('promotions/{cartPriceRule}', [PromotionController::class, 'destroy'])->middleware('permission:promotions.delete')->name('promotions.destroy');

        // Reglas de catálogo (descuentos automáticos de precio)
        Route::get('catalog-rules', [CatalogRuleController::class, 'index'])->name('catalog-rules.index');
        Route::get('catalog-rules/create', [CatalogRuleController::class, 'create'])->middleware('permission:promotions.create')->name('catalog-rules.create');
        Route::post('catalog-rules', [CatalogRuleController::class, 'store'])->middleware('permission:promotions.create')->name('catalog-rules.store');
        Route::get('catalog-rules/{catalogRule}/edit', [CatalogRuleController::class, 'edit'])->middleware('permission:promotions.edit')->name('catalog-rules.edit');
        Route::put('catalog-rules/{catalogRule}', [CatalogRuleController::class, 'update'])->middleware('permission:promotions.edit')->name('catalog-rules.update');
        Route::delete('catalog-rules/{catalogRule}', [CatalogRuleController::class, 'destroy'])->middleware('permission:promotions.delete')->name('catalog-rules.destroy');
    });

    // Auditoría (registro de acciones administrativas)
    Route::middleware('permission:audit.view')->group(function () {
        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
    });

    // Ventas: órdenes
    Route::middleware('permission:sales.orders.view')->group(function () {
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::put('orders/{order}/status', [OrderController::class, 'updateStatus'])->middleware('permission:sales.orders.edit')->name('orders.status');
        Route::post('orders/{order}/comment', [OrderController::class, 'addComment'])->middleware('permission:sales.orders.edit')->name('orders.comment');
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->middleware('permission:sales.orders.cancel')->name('orders.cancel');
    });

    // Ventas: facturas
    Route::middleware('permission:sales.invoices.view')->group(function () {
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::post('invoices', [InvoiceController::class, 'store'])->middleware('permission:sales.invoices.create')->name('invoices.store');
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->middleware('permission:sales.invoices.cancel')->name('invoices.cancel');
        Route::post('invoices/{invoice}/paid', [InvoiceController::class, 'markAsPaid'])->middleware('permission:sales.invoices.edit')->name('invoices.mark-as-paid');
    });

    // Ventas: envíos
    Route::middleware('permission:sales.shipments.view')->group(function () {
        Route::get('shipments', [ShipmentController::class, 'index'])->name('shipments.index');
        Route::get('shipments/{shipment}', [ShipmentController::class, 'show'])->name('shipments.show');
        Route::post('shipments', [ShipmentController::class, 'store'])->middleware('permission:sales.shipments.create')->name('shipments.store');
        Route::post('shipments/{shipment}/ship', [ShipmentController::class, 'markShipped'])->middleware('permission:sales.shipments.edit')->name('shipments.ship');
        Route::post('shipments/{shipment}/deliver', [ShipmentController::class, 'markDelivered'])->middleware('permission:sales.shipments.edit')->name('shipments.deliver');
        Route::post('shipments/{shipment}/cancel', [ShipmentController::class, 'cancel'])->middleware('permission:sales.shipments.cancel')->name('shipments.cancel');
    });

    // Envíos: métodos globales + configuración por tienda
    Route::middleware('permission:settings.shipping')->group(function () {
        Route::get('shipping', [ShippingMethodController::class, 'index'])->name('shipping.index');
        Route::get('shipping/create', [ShippingMethodController::class, 'create'])->name('shipping.create');
        Route::post('shipping', [ShippingMethodController::class, 'store'])->name('shipping.store');
        Route::get('shipping/{shipping}/edit', [ShippingMethodController::class, 'edit'])->name('shipping.edit');
        Route::put('shipping/{shipping}', [ShippingMethodController::class, 'update'])->name('shipping.update');
        Route::delete('shipping/{shipping}', [ShippingMethodController::class, 'destroy'])->name('shipping.destroy');

        Route::get('shipping-stores', [StoreShippingController::class, 'edit'])->name('shipping-stores.edit');
        Route::put('shipping-stores', [StoreShippingController::class, 'update'])->name('shipping-stores.update');
    });

    // Encabezado: cintillo (por website) + menú del header (por tienda)
    Route::middleware('permission:settings.storefront')->group(function () {
        Route::get('header-settings', [HeaderSettingsController::class, 'edit'])->name('header-settings.edit');
        Route::put('header-settings', [HeaderSettingsController::class, 'update'])->name('header-settings.update');
        Route::post('header-settings/image', [HeaderSettingsController::class, 'uploadImage'])->name('header-settings.image');

        Route::get('header-menu', [HeaderMenuController::class, 'index'])->name('header-menu.index');
        Route::post('header-menu', [HeaderMenuController::class, 'store'])->name('header-menu.store');
        Route::put('header-menu/{headerMenuItem}', [HeaderMenuController::class, 'update'])->name('header-menu.update');
        Route::delete('header-menu/{headerMenuItem}', [HeaderMenuController::class, 'destroy'])->name('header-menu.destroy');
        Route::post('header-menu/reorder', [HeaderMenuController::class, 'reorder'])->name('header-menu.reorder');

        Route::get('storefront/pages/home', [StorefrontPageController::class, 'home'])->name('storefront.pages.home');
        Route::put('storefront/pages/home', [StorefrontPageController::class, 'updateHome'])->name('storefront.pages.home.update');
        Route::get('storefront/pages', [StorefrontPageController::class, 'index'])->name('storefront.pages.index');
        Route::post('storefront/pages', [StorefrontPageController::class, 'store'])->name('storefront.pages.store');
        Route::get('storefront/pages/{page}/edit', [StorefrontPageController::class, 'edit'])->name('storefront.pages.edit');
        Route::put('storefront/pages/{page}', [StorefrontPageController::class, 'update'])->name('storefront.pages.update');
        Route::delete('storefront/pages/{page}', [StorefrontPageController::class, 'destroy'])->name('storefront.pages.destroy');
        Route::post('storefront/pages/{page}/sections', [StorefrontPageController::class, 'storeSection'])->name('storefront.pages.sections.store');
        Route::put('storefront/pages/{page}/sections/{section}', [StorefrontPageController::class, 'updateSection'])->name('storefront.pages.sections.update');
        Route::delete('storefront/pages/{page}/sections/{section}', [StorefrontPageController::class, 'destroySection'])->name('storefront.pages.sections.destroy');
        Route::post('storefront/pages/{page}/sections/reorder', [StorefrontPageController::class, 'reorderSections'])->name('storefront.pages.sections.reorder');
    });

    // Biblioteca de medios
    Route::middleware('permission:media.view')->group(function () {
        Route::get('media', [MediaController::class, 'index'])->name('media.index');
        Route::post('media', [MediaController::class, 'store'])->middleware('permission:media.upload')->name('media.store');
        Route::put('media/{media}', [MediaController::class, 'update'])->middleware('permission:media.upload')->name('media.update');
        Route::delete('media/{media}', [MediaController::class, 'destroy'])->middleware('permission:media.delete')->name('media.destroy');
    });

    // Cambio de scope (cualquier admin autenticado).
    Route::post('scope', [StoreScopeController::class, 'update'])->name('scope.update');
});
