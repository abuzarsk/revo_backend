<?php

// Front Controller - Single Entry Point (API)

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS,PUT,PATCH,DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load Composer autoloader if available
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Load configuration
require_once __DIR__ . '/../app/config.php';

// Autoload classes
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Get the URL path
$url = $_SERVER['REQUEST_URI'];

// Remove query string
$url = parse_url($url, PHP_URL_PATH);


//For Development
// IMPORTANT: this must match the base path for the revo2 app
$basePath = '/www/revo2/public';

//For Production
//$basePath = '/apis/public';

if (strpos($url, $basePath) === 0) {
    $url = substr($url, strlen($basePath));
}

// Strip front-controller script name when URL is /index.php/...
$url = preg_replace('#^/index\.php#', '', $url);

// Normalize the URL - remove leading/trailing slashes and add single leading slash
$url = trim($url, '/');
$url = $url ? '/' . $url : '/';

// Remove /api prefix if present (optional, for cleaner URLs)
$url = preg_replace('#^/api#', '', $url);
$url = $url ?: '/';

// Initialize Router
$router = new App\Core\Router();

// Auth routes
//$router->addRoute('/auth/login', ['controller' => 'auth', 'action' => 'login'], 'POST'); // User login
//$router->addRoute('/auth/google-login', ['controller' => 'auth', 'action' => 'googleCallback'], 'GET'); // Google OAuth callback

$router->addRoute('/auth/signup', ['controller' => 'auth', 'action' => 'signup'], 'POST'); // User login
$router->addRoute('/auth/login', ['controller' => 'auth', 'action' => 'login'], 'POST'); // User login
$router->addRoute('/auth/google-login', ['controller' => 'auth', 'action' => 'googleCallback'], 'GET'); // Google OAuth callback
$router->addRoute('/auth/change-role', ['controller' => 'auth', 'action' => 'changeRole'], 'POST'); // Change role

$router->addRoute('/auth/check-email', ['controller' => 'auth', 'action' => 'checkEmail'], 'POST'); // Check role

$router->addRoute('/auth/product-form', ['controller' => 'auth', 'action' => 'productForm'], 'POST'); // Check username


//ONLY ADMIN CAN ACCESS THESE ROUTES (role == 1)
$router->addRoute('/admin/allcompanies', ['controller' => 'revoCompany', 'action' => 'index'], 'GET'); // List companies
$router->addRoute('/admin/companyrequests', ['controller' => 'revoUserRoleRequest', 'action' => 'forRole2'], 'GET'); // Role requests where revo_role_id=2
$router->addRoute('/admin/mediators', ['controller' => 'revoMediator', 'action' => 'index'], 'GET'); // Get all mediators
$router->addRoute('/admin/users', ['controller' => 'revoUser', 'action' => 'index'], 'GET');          // List users (with pagination, optional)
$router->addRoute('/admin/companies/verify', ['controller' => 'revoCompany', 'action' => 'verify'], 'POST'); // Verify company
$router->addRoute('/admin/companies/pause', ['controller' => 'revoCompany', 'action' => 'pause'], 'POST');   // Pause company (set status = 3)
$router->addRoute('/admin/companies/cancel', ['controller' => 'revoCompany', 'action' => 'cancel'], 'POST'); // Cancel company (set status = 5)
$router->addRoute('/admin/companies/resume', ['controller' => 'revoCompany', 'action' => 'resume'], 'POST'); // Resume company (set status = 1)
$router->addRoute('/admin/companyrequests/reject', ['controller' => 'revoCompany', 'action' => 'rejectRoleRequest'], 'POST'); // Reject role request (company.status=4, request.status=4)



//OTHER THAN ADMIN CAN ACCESS THESE ROUTES (role == 2, 3, 4)
// Brand routes
$router->addRoute('/brands/{id:\d+}/update', ['controller' => 'revoBrand', 'action' => 'update'], 'POST'); // Update brand (alias for PUT/PATCH)

$router->addRoute('/brands', ['controller' => 'revoBrand', 'action' => 'index'], 'GET'); // List company brands (paginated, default 1 per page)
$router->addRoute('/brands', ['controller' => 'revoBrand', 'action' => 'create'], 'POST'); // Create new brand

$router->addRoute('/getBrandsList', ['controller' => 'revoBrand', 'action' => 'getbrandslist'], 'GET'); // Show brand by ID

// Product routes
//$router->addRoute('/products', ['controller' => 'revoProduct', 'action' => 'index'], 'GET'); // List all products
$router->addRoute('/products/company', ['controller' => 'revoProduct', 'action' => 'byCompany'], 'GET'); // List company products (paginated, default 1 per page)
$router->addRoute('/products/{id:\d+}/update', ['controller' => 'revoProduct', 'action' => 'update'], 'POST'); // Update product
$router->addRoute('/products', ['controller' => 'revoProduct', 'action' => 'create'], 'POST'); // Create new product
$router->addRoute('/products/promotedemote', ['controller' => 'revoProduct', 'action' => 'promoteDemoteProduct'], 'POST'); // Delete product
$router->addRoute('/products/{id:\d+}', ['controller' => 'revoProduct', 'action' => 'show'], 'GET'); // Update product

$router->addRoute('/products/{id:\d+}/excel', ['controller' => 'revoFormSubmission', 'action' => 'getExcel'], 'GET'); // Update product



$router->addRoute('/form/{id:[0-9]+}', ['controller' => 'revoForm', 'action' => 'show'], 'GET'); // Get form by ID (alias)

// $router->addRoute('/forms/product', ['controller' => 'revoForm', 'action' => 'byProduct'], 'GET'); // List forms for a product
// $router->addRoute('/forms', ['controller' => 'revoForm', 'action' => 'create'], 'POST'); // Create new form
$router->addRoute('/form/update', ['controller' => 'revoForm', 'action' => 'update'], 'POST'); // Update form
// $router->addRoute('/form-submissions/form', ['controller' => 'revoFormSubmission', 'action' => 'byForm'], 'GET'); // List submissions for a form
// $router->addRoute('/form-submissions/user', ['controller' => 'revoFormSubmission', 'action' => 'byUser'], 'GET'); // List submissions for logged-in user
// $router->addRoute('/form-submissions', ['controller' => 'revoFormSubmission', 'action' => 'create'], 'POST'); // Create new form submission
// $router->addRoute('/form-submissions/update', ['controller' => 'revoFormSubmission', 'action' => 'update'], 'POST'); // Update form submission

$router->addRoute('/company/connected-mediators', ['controller' => 'revoConnect', 'action' => 'companiesconnectedmediator'], 'GET'); // Update connect
$router->addRoute('/company/requested-mediators', ['controller' => 'revoConnect', 'action' => 'companiesrequestedmediator'], 'GET'); // Update connect
$router->addRoute(
    '/company/rejected-mediators',
    ['controller' => 'revoConnect', 'action' => 'companyrejectedmediator'],
    'GET'
);// Define API Routes
$router->addRoute('/company/verify-mediator', ['controller' => 'revoConnect', 'action' => 'verifymediator'], 'POST'); // Update connect
$router->addRoute('/company/search/{alias:[A-Za-z0-9_\\-]+}',['controller' => 'revoCompany', 'action' => 'getbyalias'],'GET');
$router->addRoute('/company/submissions', ['controller' => 'revoFormSubmission', 'action' => 'companyList'], 'GET'); // All company submissions (paginated)
$router->addRoute('/company/refunds', ['controller' => 'revoRefundSubmission', 'action' => 'companyList'], 'GET'); // All company refunds; expected price = product price - less
$router->addRoute('/company/form-submissions/{id:[0-9]+}', ['controller' => 'revoFormSubmission', 'action' => 'getAllByFormSubmissions'], 'GET'); // Form submissions by promote_id (paginated)
$router->addRoute('/company/refundForm-submissions/{id:[0-9]+}', ['controller' => 'revoRefundSubmission', 'action' => 'getForCompanyAndMediatorBySubmissionId'], 'GET'); // Refund submissions by submission_id (paginated, default 1 per page)
$router->addRoute('/company/refundForm-submissions/update', ['controller' => 'revoRefundSubmission', 'action' => 'updateAcceptOrRejectStatus'], 'POST'); // Update refund accept/reject status
$router->addRoute('/mediator/refundForm-submissions/{id:[0-9]+}', ['controller' => 'revoRefundSubmission', 'action' => 'getForCompanyAndMediatorBySubmissionId'], 'GET'); // Same as company route (paginated)
//$router->addRoute('/mediator/refundForm-submissions/update', ['controller' => 'revoRefundSubmission', 'action' => 'updateAcceptStatus'], 'POST'); // Update refund accept/reject status

// Connect routes
$router->addRoute('/revo-connects', ['controller' => 'revoConnect', 'action' => 'create'], 'POST'); // Create new connect
$router->addRoute('/revo-connects/update', ['controller' => 'revoConnect', 'action' => 'update'], 'POST'); // Create new connect


//GET PROMOTE ID OF PRODUCT According to USER ROLE
$router->addRoute('/products/promote/{id:[0-9]+}', ['controller' => 'revoProduct', 'action' => 'getPromoteIdByProductId'], 'GET'); // Get promote id by product id

//ONLY FOR MEDIATORS (role == 3)
$router->addRoute('/mediator/connected-companies', ['controller' => 'revoConnect', 'action' => 'mediatorconnectedcompanies'], 'GET'); // Update connect
$router->addRoute('/mediator/requested-companies', ['controller' => 'revoConnect', 'action' => 'mediatorrequestedcompanies'], 'GET'); // Update connect
$router->addRoute(
    '/mediator/rejected-companies',
    ['controller' => 'revoConnect', 'action' => 'mediatorrejectedcompany'],
    'GET'
);// Define API Routes
$router->addRoute('/mediator/products', ['controller' => 'revoProduct', 'action' => 'getMediatorProducts'], 'GET'); // Mediator products (paginated, default 1 per page)
$router->addRoute('/mediator/products/{id:[0-9]+}', ['controller' => 'revoProduct', 'action' => 'getMediatorProductById'], 'GET'); // Mediator product by ID
$router->addRoute('/mediator/form-submissions/{id:[0-9]+}', ['controller' => 'revoFormSubmission', 'action' => 'getAllByFormSubmissions'], 'GET'); // Form submissions by promote_id (paginated)
$router->addRoute('/mediator/refundForm-submissions/update', ['controller' => 'revoRefundSubmission', 'action' => 'updateAcceptOrRejectStatus'], 'POST'); // Update refund accept/reject status

//$router->addRoute('/products/promote', ['controller' => 'revoProductPromote', 'action' => 'index'], 'GET'); // List mediator promotions
$router->addRoute('/products/mediator/promote', ['controller' => 'revoProductPromote', 'action' => 'create'], 'POST'); // Create promotion
// $router->addRoute('/products/promotions/{id:\d+}', ['controller' => 'revoProductPromote', 'action' => 'show'], 'GET'); // Get promotion
// $router->addRoute('/products/promotions/{id:\d+}', ['controller' => 'revoProductPromote', 'action' => 'delete'], 'DELETE'); // Delete promotion
// $router->addRoute('/products/promotions/update', ['controller' => 'revoProductPromote', 'action' => 'update'], 'POST'); // Update promotion




//ONLY FOR USERS (role == 4)
$router->addRoute('/search/{alias:[A-Za-z0-9_\\-]+}',['controller' => 'revoCompany', 'action' => 'searchbyalias'],'GET');
$router->addRoute('/user/connections', ['controller' => 'revoConnect', 'action' => 'getallconnections'], 'GET'); // Create new connect
$router->addRoute('/user/products', ['controller' => 'revoProduct', 'action' => 'getUserProducts'], 'GET'); // User products (paginated, default 1 per page)
$router->addRoute('/user/form/promote/{id:[0-9]+}', ['controller' => 'revoForm', 'action' => 'getFormByPromoteId'], 'GET'); // Get form by ID
$router->addRoute('/user/products/promote/{id:[0-9]+}', ['controller' => 'revoProduct', 'action' => 'getProductByPromoteId'], 'GET'); // Update connect
$router->addRoute('/user/form-submissions', ['controller' => 'revoFormSubmission', 'action' => 'create'], 'POST'); // Create new form submission
$router->addRoute('/user/form-submissions/{id:[0-9]+}', ['controller' => 'revoFormSubmission', 'action' => 'getUserSubmissions'], 'GET'); // User submissions by promote_id (paginated, default 1 per page)
// $router->addRoute('/user/refundForm-submissions/{id:[0-9]+}', ['controller' => 'revoFormSubmission', 'action' => 'getUserRefundSubmissions'], 'GET'); // Refund form submissions (paginated)


$router->addRoute('/user/form/refund/{id:[0-9]+}', ['controller' => 'revoFormSubmission', 'action' => 'getRefundFormBySubmissionId'], 'GET'); // Get form by ID
$router->addRoute('/user/products/refund/{id:[0-9]+}', ['controller' => 'revoProduct', 'action' => 'getProductByRefundFormId'], 'GET'); // Update connect
// $router->addRoute('/user/refundForm-submissions/{id:[0-9]+}', ['controller' => 'revoFormSubmission', 'action' => 'getUserRefundSubmissions'], 'GET'); // Refund form submissions (form_type=2)
// $router->addRoute('/user/refund-submissions/user', ['controller' => 'revoRefundSubmission', 'action' => 'byUser'], 'GET');
// $router->addRoute('/user/refund-submissions/update', ['controller' => 'revoRefundSubmission', 'action' => 'update'], 'POST');
$router->addRoute('/user/refundForm-submissions', ['controller' => 'revoRefundSubmission', 'action' => 'create'], 'POST');
$router->addRoute('/user/refundForm-submissions/{id:[0-9]+}', ['controller' => 'revoRefundSubmission', 'action' => 'getBySubmissionId'], 'GET'); // Refund submissions by submission_id (paginated)


$router->addRoute('/user/bank', ['controller' => 'revoUserBank', 'action' => 'show'], 'GET'); // Get logged-in user bank details
$router->addRoute('/user/bank', ['controller' => 'revoUserBank', 'action' => 'create'], 'POST'); // Create bank details
$router->addRoute('/user/bank/update', ['controller' => 'revoUserBank', 'action' => 'update'], 'POST'); // Update bank details
// $router->addRoute('/user/bank', ['controller' => 'revoUserBank', 'action' => 'delete'], 'DELETE'); // Delete bank details
$router->addRoute('/user/order-history', ['controller' => 'revoOrderHistory', 'action' => 'index'], 'GET'); // User order history (paginated)
$router->addRoute('/user/order-history/{id:[0-9]+}', ['controller' => 'revoOrderHistory', 'action' => 'show'], 'GET'); // Single order history by submission id

$router->addRoute('/user/formimageupload', ['controller' => 'revoUser', 'action' => 'formimageupload'], 'POST'); // Upload image

$router->addRoute('/dashboard', ['controller' => 'revoProduct', 'action' => 'dashboard'], 'GET'); // Dashboard
// Home
$router->addRoute('', ['controller' => 'home', 'action' => 'index']);
$router->addRoute('/', ['controller' => 'home', 'action' => 'index']);
    


// Dispatch the request
try {
    $router->dispatch($url);
} catch (\Exception $e) {
    $statusCode =  $e->getCode() && $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
    http_response_code((int)$statusCode);
    
    $response = [
        'success' => false,
        'message' => $e->getMessage() ?: 'An error occurred'
    ];
    
    if (ENVIRONMENT === 'development') {
        $response['file'] = $e->getFile();
        $response['line'] = $e->getLine();
        $response['trace'] = $e->getTraceAsString();
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

