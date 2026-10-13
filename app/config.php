<?php

// Application Configuration

// Load secrets from project-root .env (never commit real values)
(function (): void {
    $envFile = dirname(__DIR__) . '/.env';
    if (!is_readable($envFile)) {
        return;
    }
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '') {
            continue;
        }
        $value = trim($value, "\"'");
        if (getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
})();

// Environment
define('ENVIRONMENT', 'development'); // development, production

// Error Reporting
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Base URL
//FOR DEVELOPMENT
define('BASE_URL', 'http://localhost/www/revo2/public/');
define('API_URL', BASE_URL);

//FOR PRODUCTION
//define('BASE_URL', 'https://revo.pinnaci.com/apis/public');

// Google OAuth — set in .env
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');
define('GOOGLE_REDIRECT_URI', getenv('GOOGLE_REDIRECT_URI') ?: BASE_URL . 'auth/google-callback.php');

// Google Sheets — only writes when product.googlesheet_url is set
define('GOOGLE_SHEETS_ENABLED', filter_var(getenv('GOOGLE_SHEETS_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN));
define('GOOGLE_SERVICE_ACCOUNT_JSON', getenv('GOOGLE_SERVICE_ACCOUNT_JSON') ?: (__DIR__ . '/../storage/service-account.json'));
define('GOOGLE_SHEETS_TAB_NAME', getenv('GOOGLE_SHEETS_TAB_NAME') ?: 'Sheet1');

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'revo2');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? (string)getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

// Database table names
define('TABLE_USER', 'user');
define('TABLE_COMPANY', 'company');
define('TABLE_MEDIATOR', 'mediator');
define('TABLE_ADMIN', 'admin');
define('TABLE_PRODUCTS', 'products');
define('TABLE_BRANDS', 'brands');
define('TABLE_FORMS', 'forms');
define('TABLE_FORM_SUBMISSION', 'form_submission');
define('TABLE_REFUND_SUBMISSION', 'refund_submission');
define('TABLE_API_ACCESS', 'api_access');
define('TABLE_APIS', 'apis');
define('TABLE_APIS_LINK_MODULE', 'apis_link_module');
define('TABLE_MODULES', 'modules');
define('TABLE_MODULE_ACCESS', 'module_access');
define('TABLE_COMPANY_USER', 'company_user');
define('TABLE_MEDIATOR_USER', 'mediator_user');
define('TABLE_MEDIATOR_COMPANY', 'mediator_company');
define('TABLE_PROMOTE_PRODUCTS', 'promote_products');
define('TABLE_TEMP_USERS', 'temp_users');

// JWT Configuration
define('JWT_SECRET', 'change-me-to-a-random-secret');
define('JWT_ISSUER', 'revo_api');
define('JWT_AUDIENCE', 'revo_clients');
define('JWT_EXPIRES_IN', 3600); // seconds

// Timezone
date_default_timezone_set('UTC');

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS

define('USER_TYPES', [
    1 => 'company',
    2 => 'mediator',
    3 => 'user'
]);

define('ROLES', [
    1 => 'admin',
    2 => 'company',
    3 => 'mediator',
    4 => 'user'
]);


define('FORM_TYPES', [
    'form' => 1,
    'refund' => 2, 
]);



define('Pagination_Default_Per_Page', 10);
define('Pagination_Default_Subsmission_Per_Page', 10);
define('Pagination_Default_Brand_Per_Page', 10);

define('USER_TYPES_REVERSE', array_flip(USER_TYPES));

// File Upload Configuration
define('UPLOAD_DIR', __DIR__ . '/../public/uploads/');
define('UPLOAD_URL', BASE_URL . 'uploads/');
define('BRAND_UPLOAD_DIR', UPLOAD_DIR . 'brands/');
define('BRAND_UPLOAD_URL', UPLOAD_URL . 'brands/');
define('PRODUCT_UPLOAD_DIR', UPLOAD_DIR . 'products/');
define('PRODUCT_UPLOAD_URL', UPLOAD_URL . 'products/');
define('COMPANY_UPLOAD_DIR', UPLOAD_DIR . 'companies/');
define('COMPANY_UPLOAD_URL', UPLOAD_URL . 'companies/');
define('MEDIATOR_UPLOAD_DIR', UPLOAD_DIR . 'mediators/');
define('MEDIATOR_UPLOAD_URL', UPLOAD_URL . 'mediators/');
define('REFUND_UPLOAD_DIR', UPLOAD_DIR . 'refunds/');
define('EXCEL_UPLOAD_DIR', UPLOAD_DIR . 'excels/');
define('FORM_IMAGE_UPLOAD_DIR', UPLOAD_DIR . 'form_images/');
define('FORM_IMAGE_URL', BASE_URL . 'uploads/form_images/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']);

 function handlePhotoUpload($file, $uploadDir)
    {
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload error', 'code' => 400];
        }

        // Check file size
        if ($file['size'] > MAX_FILE_SIZE) {
            return ['success' => false, 'message' => 'File size exceeds maximum allowed size (5MB)', 'code' => 400];
        }

        // Validate file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
            return ['success' => false, 'message' => 'Invalid file type. Allowed types: JPEG, PNG, GIF, WebP', 'code' => 400];
        }

        // Create upload directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return ['success' => false, 'message' => 'Failed to create upload directory', 'code' => 500];
            }
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('brand_', true) . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => false, 'message' => 'Failed to save uploaded file', 'code' => 500];
        }

        return ['success' => true, 'filename' => $filename, 'url' => FORM_IMAGE_URL . $filename];
    }

     function handleBase64Photo($base64String, $uploadDir)
    {
        // Check if it's a data URL
        if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $matches)) {
            $imageType = $matches[1];
            $base64String = substr($base64String, strpos($base64String, ',') + 1);
        } else {
            // Try to detect image type from base64
            $imageType = 'jpg'; // default
        }

        // Validate image type
        $allowedTypes = ['jpeg', 'jpg', 'png', 'gif', 'webp'];
        if (!in_array(strtolower($imageType), $allowedTypes)) {
            return ['success' => false, 'message' => 'Invalid image type. Allowed types: JPEG, PNG, GIF, WebP', 'code' => 400];
        }

        // Decode base64
        $imageData = base64_decode($base64String);
        if ($imageData === false) {
            return ['success' => false, 'message' => 'Invalid base64 image data', 'code' => 400];
        }

        // Check file size
        if (strlen($imageData) > MAX_FILE_SIZE) {
            return ['success' => false, 'message' => 'File size exceeds maximum allowed size (5MB)', 'code' => 400];
        }

        // Validate it's actually an image
        $tempFile = tmpfile();
        $tempPath = stream_get_meta_data($tempFile)['uri'];
        file_put_contents($tempPath, $imageData);
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tempPath);
        finfo_close($finfo);
        fclose($tempFile);

        if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
            return ['success' => false, 'message' => 'Invalid file type. Allowed types: JPEG, PNG, GIF, WebP', 'code' => 400];
        }

        // Create upload directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return ['success' => false, 'message' => 'Failed to create upload directory', 'code' => 500];
            }
        }

        // Generate unique filename
        $extension = $imageType === 'jpeg' ? 'jpg' : $imageType;
        $filename = uniqid('brand_', true) . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;

        // Save file
        if (file_put_contents($filepath, $imageData) === false) {
            return ['success' => false, 'message' => 'Failed to save image file', 'code' => 500];
        }

        return ['success' => true, 'filename' => $filename];
    }