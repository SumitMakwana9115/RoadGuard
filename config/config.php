<?php
/**
 * Application Configuration
 * Personalized for Enrollment: 230210107035 | U = 35
 */

// Base path
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', '/WP');

// Personalization
define('ENROLLMENT_NO', '230210107035');
define('UNIQUE_CODE', 35);
define('COMPLAINT_DOMAIN', 'Road / Pathway Surface Damage');
define('AREA_MODEL', 'Ward → Area → Spot');
define('INITIAL_RESPONSE_SLA_HOURS', 7);
define('RESOLUTION_SLA_HOURS', 48);
define('SPECIAL_RULE', 'repeated_flagging'); // U is odd
define('MANDATORY_REPORT', 'Reopened complaints summary');
define('REPEATED_COMPLAINT_DAYS', 7);

// App info
define('APP_NAME', 'RoadGuard');
define('APP_TAGLINE', 'Road & Pathway Complaint Tracking System');
define('APP_VERSION', '1.0.0');

// Upload config
define('UPLOAD_DIR', BASE_PATH . '/uploads/');
define('COMPLAINT_PROOF_DIR', UPLOAD_DIR . 'complaint_proof/');
define('ACTION_PROOF_DIR', UPLOAD_DIR . 'action_proof/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg','jpeg','png','gif','pdf','doc','docx']);
define('ALLOWED_MIME_TYPES', [
    'image/jpeg','image/png','image/gif',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
]);

// Session config
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Include database
require_once __DIR__ . '/db.php';
