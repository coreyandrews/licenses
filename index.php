<?php
// Define the path for the SQLite database file
$dbFile = '/var/www/html/data/licenses.sqlite';
$uploadDir = '/var/www/html/uploads/'; // Directory to store uploaded files

// Ensure directories exist and are writable
if (!is_dir(dirname($dbFile))) {
    mkdir(dirname($dbFile), 0777, true);
}
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Connect to SQLite database
try {
    $pdo = new PDO("sqlite:" . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create documents table if it doesn't exist
    // Document type is UNIQUE to prevent multiple entries for the same license type
    $pdo->exec("CREATE TABLE IF NOT EXISTS documents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        document_type TEXT NOT NULL UNIQUE, -- User-defined document type (e.g., 'Drivers License', 'Radio Permit')
        file_path TEXT NOT NULL,
        original_filename TEXT NOT NULL,
        upload_date TEXT NOT NULL
    )");
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$message = $_GET['message'] ?? '';
$messageType = $_GET['type'] ?? '';

// Fetch all uploaded documents for display
// Order by document_type for consistent display, then by upload_date
$stmt = $pdo->query("SELECT * FROM documents ORDER BY document_type ASC, upload_date DESC");
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Licenses</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Favicon and Apple Touch Icons -->
    <link rel="icon" href="logo.png" type="image/png">
    <link rel="apple-touch-icon" href="logo.png">
    <link rel="apple-touch-icon" sizes="120x120" href="logo.png">
    <link rel="apple-touch-icon" sizes="152x152" href="logo.png">
    <link rel="apple-touch-icon" sizes="167x167" href="logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="logo.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Licenses">


    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        /* Adjusted container max-width for better mobile experience */
        .container {
            max-width: 100%; /* Full width on small screens */
        }
        @media (min-width: 640px) { /* Small screens and up */
            .container {
                max-width: 640px; /* Max width for tablets */
            }
        }
        @media (min-width: 768px) { /* Medium screens and up */
            .container {
                max-width: 768px; /* Max width for larger tablets/small desktops */
            }
        }
        @media (min-width: 1024px) { /* Large screens and up */
            .container {
                max-width: 960px; /* Original max width for desktops */
            }
        }

        /* Modal styles (kept for consistency, though not used directly on this page anymore) */
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 20px;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            width: 90%;
            max-width: 400px;
        }
    </style>
</head>
<body class="bg-gray-100 p-2 sm:p-4">
    <div class="container mx-auto bg-white shadow-lg rounded-xl p-4 sm:p-6 md:p-8 my-4 sm:my-8">
        <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-center text-gray-800 mb-4 sm:mb-8">Licenses</h1>

        <?php if ($message): ?>
            <div class="rounded-lg p-3 sm:p-4 mb-4 sm:mb-6
                <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700 border border-green-400' : 'bg-red-100 text-red-700 border border-red-400'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Quick Access to Documents (Now displays all uploaded documents) -->
        <div class="mb-6 sm:mb-8 p-4 sm:p-6 bg-gray-50 rounded-lg shadow-sm">
            <h2 class="text-xl sm:text-2xl font-semibold text-gray-700 mb-3 sm:mb-4">Quick Document Access</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                <?php if (empty($documents)): ?>
                    <span class="inline-flex justify-center items-center text-center py-2 sm:py-3 px-4 sm:px-6 border border-gray-300 rounded-md text-gray-600 bg-white text-sm sm:text-base md:col-span-3">
                        No licenses uploaded yet.
                    </span>
                <?php else: ?>
                    <?php foreach ($documents as $doc): ?>
                        <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank"
                           class="inline-flex justify-center items-center text-center py-2 sm:py-3 px-4 sm:px-6 border border-transparent shadow-sm text-sm sm:text-base font-medium rounded-md text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition duration-150 ease-in-out">
                            View <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $doc['document_type']))); ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Navigation Buttons -->
        <div class="mb-6 sm:mb-8 p-4 sm:p-6 bg-gray-50 rounded-lg shadow-sm flex flex-col sm:flex-row justify-center gap-4">
            <a href="history.php"
               class="inline-flex justify-center py-3 px-6 border border-transparent shadow-sm text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out w-full sm:w-auto">
                View History
            </a>
            <a href="upload_license.php"
               class="inline-flex justify-center py-3 px-6 border border-transparent shadow-sm text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150 ease-in-out w-full sm:w-auto">
                Upload New License
            </a>
        </div>


    </div>
</body>
</html>
