<?php
/**
 * Database Connection & Utility Functions
 * InfinityFree – PRODUCTION READY
 */

// Start session if not already started (suppress errors for deployment)
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// ============================================================================
// DATABASE CONFIGURATION (DEPLOYMENT FRIENDLY)
// ============================================================================

// Use environment variables if set, otherwise fallback to defaults
define('DB_HOST', getenv('DB_HOST') ?: 'sql308.infinityfree.com');
define('DB_USER', getenv('DB_USER') ?: 'if0_40678472');
define('DB_PASS', getenv('DB_PASS') ?: 'rlKv94jqhMe1W');
define('DB_NAME', getenv('DB_NAME') ?: 'if0_40678472_laundry_db');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// Disable debug mode for production by default
define('DB_DEBUG', getenv('DB_DEBUG') === 'true');

// ============================================================================
// DATABASE CONNECTION
// ============================================================================

try {
    // Suppress errors for deployment, handle via exceptions
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        throw new Exception($conn->connect_error);
    }

    if (!$conn->set_charset(DB_CHARSET)) {
        throw new Exception($conn->error);
    }

    // Ensure connection is accessible globally for legacy code that expects $conn
    $GLOBALS['conn'] = $conn;

} catch (Exception $e) {
    if (DB_DEBUG) {
        die("❌ Database Connection Error: " . $e->getMessage());
    } else {
        // Generic error for production
        die("❌ Unable to connect to database.");
    }
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function esc($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function getDatabase() {
    global $conn;
    return $conn;
}

// Close DB on shutdown
register_shutdown_function(function () {
    global $conn;
    if ($conn) {
        $conn->close();
    }
});
?>
