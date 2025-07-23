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
    $pdo->exec("CREATE TABLE IF NOT EXISTS documents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        document_type TEXT NOT NULL UNIQUE,
        file_path TEXT NOT NULL,
        original_filename TEXT NOT NULL,
        upload_date TEXT NOT NULL
    )");
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$message = '';
$messageType = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_document') {
    $documentType = trim($_POST['document_type'] ?? '');

    // Normalize document type for storage (e.g., replace spaces with underscores, lowercase)
    $normalizedDocumentType = strtolower(str_replace(' ', '_', $documentType));

    // Check for upload errors first
    if (empty($documentType) || empty($normalizedDocumentType) || !isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
        $messageType = 'error';
        $uploadError = $_FILES['document_file']['error'] ?? UPLOAD_ERR_NO_FILE; // Default to no file if $_FILES isn't set

        switch ($uploadError) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $message = 'The uploaded file exceeds the maximum file size allowed by the server (check php.ini settings).';
                break;
            case UPLOAD_ERR_PARTIAL:
                $message = 'The uploaded file was only partially uploaded. Please try again.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $message = 'No file was uploaded. Please select a file.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $message = 'Missing a temporary folder for uploads. This is a server configuration issue.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $message = 'Failed to write file to disk. Check server permissions for the temporary upload directory.';
                break;
            case UPLOAD_ERR_EXTENSION:
                $message = 'A PHP extension stopped the file upload.';
                break;
            default:
                $message = 'An unknown file upload error occurred (Error Code: ' . $uploadError . ').';
                break;
        }
        // If document type is empty but file is OK, give specific message
        if ((empty($documentType) || empty($normalizedDocumentType)) && isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
            $message = 'Please enter a document type.';
        }
    } elseif (!in_array(strtolower(pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION)), ['pdf'])) {
        $message = 'Only PDF files are allowed.';
        $messageType = 'error';
    } elseif ($_FILES['document_file']['size'] > 10 * 1024 * 1024) { // 10 MB limit for documents
        $message = 'File size exceeds the 10MB limit set by the application.';
        $messageType = 'error';
    } else {
        $file = $_FILES['document_file'];
        $originalFilename = basename($file['name']);
        $fileExtension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        // Generate a unique filename to prevent conflicts
        $uniqueFilename = uniqid('license_doc_', true) . '.' . $fileExtension;
        $destinationPath = $uploadDir . $uniqueFilename;

        if (move_uploaded_file($file['tmp_name'], $destinationPath)) {
            try {
                // Check if a document of this type already exists using the normalized name
                $stmt = $pdo->prepare("SELECT id, file_path FROM documents WHERE document_type = :document_type");
                $stmt->execute([':document_type' => $normalizedDocumentType]);
                $existingDoc = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existingDoc) {
                    // Update existing entry and delete old file
                    $stmt = $pdo->prepare("UPDATE documents SET file_path = :file_path, original_filename = :original_filename, upload_date = :upload_date WHERE id = :id");
                    $stmt->execute([
                        ':file_path' => 'uploads/' . $uniqueFilename, // Store relative path
                        ':original_filename' => $originalFilename,
                        ':upload_date' => date('Y-m-d H:i:s'),
                        ':id' => $existingDoc['id']
                    ]);
                    // Delete the old file if it exists
                    if (file_exists('/var/www/html/' . $existingDoc['file_path'])) {
                        unlink('/var/www/html/' . $existingDoc['file_path']);
                    }
                    $message = 'Document updated successfully.';
                } else {
                    // Insert new entry
                    $stmt = $pdo->prepare("INSERT INTO documents (document_type, file_path, original_filename, upload_date) VALUES (:document_type, :file_path, :original_filename, :upload_date)");
                    $stmt->execute([
                        ':document_type' => $normalizedDocumentType, // Store normalized name
                        ':file_path' => 'uploads/' . $uniqueFilename, // Store relative path
                        ':original_filename' => $originalFilename,
                        ':upload_date' => date('Y-m-d H:i:s')
                    ]);
                    $message = 'Document uploaded successfully.';
                }
                $messageType = 'success';
            } catch (PDOException $e) {
                // If DB insertion fails, delete the uploaded file to prevent orphans
                unlink($destinationPath);
                if ($e->getCode() == '23000') { // SQLite unique constraint violation
                    $message = 'A document with this type ("' . htmlspecialchars($documentType) . '") already exists. Please choose a different name or delete the existing one to update it.';
                } else {
                    $message = 'Error saving document details to database: ' . $e->getMessage();
                }
                $messageType = 'error';
            }
        } else {
            $message = 'Failed to move uploaded file to the uploads directory. Check directory permissions.';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload License</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        .container {
            max-width: 100%;
        }
        @media (min-width: 640px) {
            .container {
                max-width: 640px;
            }
        }
        @media (min-width: 768px) {
            .container {
                max-width: 768px;
            }
        }
        @media (min-width: 1024px) {
            .container {
                max-width: 960px;
            }
        }
    </style>
</head>
<body class="bg-gray-100 p-2 sm:p-4">
    <div class="container mx-auto bg-white shadow-lg rounded-xl p-4 sm:p-6 md:p-8 my-4 sm:my-8">
        <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-center text-gray-800 mb-4 sm:mb-8">Upload New License</h1>

        <div class="mb-6">
            <a href="index.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                &larr; Back to Licenses
            </a>
        </div>

        <?php if ($message): ?>
            <div class="rounded-lg p-3 sm:p-4 mb-4 sm:mb-6
                <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700 border border-green-400' : 'bg-red-100 text-red-700 border border-red-400'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Upload Form -->
        <div class="p-4 sm:p-6 bg-gray-50 rounded-lg shadow-sm">
            <h2 class="text-xl sm:text-2xl font-semibold text-gray-700 mb-3 sm:mb-4">Upload Document</h2>
            <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4 items-end">
                <input type="hidden" name="action" value="upload_document">
                <div>
                    <label for="document_type" class="block text-sm font-medium text-gray-700">Document Type (e.g., Driver's License)</label>
                    <input type="text" id="document_type" name="document_type" required placeholder="Enter license type"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-2 border bg-white text-sm">
                </div>
                <div>
                    <label for="document_file" class="block text-sm font-medium text-gray-700">Select PDF File (Max 10MB)</label>
                    <input type="file" id="document_file" name="document_file" accept=".pdf" required
                           class="mt-1 block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-white focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                </div>
                <div class="md:col-span-2">
                    <button type="submit"
                            class="inline-flex justify-center py-2 px-6 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150 ease-in-out w-full">
                        Upload Document
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
