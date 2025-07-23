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

// Handle document deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_document') {
    $docId = filter_var($_POST['doc_id'] ?? '', FILTER_VALIDATE_INT);

    if ($docId === false || $docId <= 0) {
        $message = 'Invalid document ID for deletion.';
        $messageType = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT file_path FROM documents WHERE id = :id");
            $stmt->execute([':id' => $docId]);
            $docToDelete = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($docToDelete) {
                $stmt = $pdo->prepare("DELETE FROM documents WHERE id = :id");
                $stmt->execute([':id' => $docId]);

                if ($stmt->rowCount() > 0) {
                    // Also delete the physical file
                    if (file_exists('/var/www/html/' . $docToDelete['file_path'])) {
                        unlink('/var/www/html/' . $docToDelete['file_path']);
                    }
                    $message = 'Document deleted successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Document not found or could not be deleted from database.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Document not found.';
                $messageType = 'error';
            }
        } catch (PDOException $e) {
            $message = 'Error deleting document: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Fetch all uploaded documents for display
$stmt = $pdo->query("SELECT * FROM documents ORDER BY upload_date DESC");
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License History</title>
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

        /* Modal styles */
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
        <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-center text-gray-800 mb-4 sm:mb-8">License History</h1>

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

        <!-- Uploaded Documents List -->
        <div class="p-4 sm:p-6 bg-gray-50 rounded-lg shadow-sm">
            <h2 class="text-xl sm:text-2xl font-semibold text-gray-700 mb-3 sm:mb-4">Uploaded Documents History</h2>
            <?php if (empty($documents)): ?>
                <p class="text-gray-600 text-sm sm:text-base">No documents uploaded yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100">
                            <tr>
                                <th scope="col" class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider rounded-tl-lg">
                                    Type
                                </th>
                                <th scope="col" class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Original Filename
                                </th>
                                <th scope="col" class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Upload Date
                                </th>
                                <th scope="col" class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider rounded-tr-lg">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($documents as $doc): ?>
                                <tr>
                                    <td class="px-3 py-2 sm:px-6 sm:py-4 whitespace-nowrap text-xs sm:text-sm font-medium text-gray-900">
                                        <?php
                                            // Convert snake_case to readable string for display
                                            $typeDisplay = ucwords(str_replace('_', ' ', $doc['document_type']));
                                            echo htmlspecialchars($typeDisplay);
                                        ?>
                                    </td>
                                    <td class="px-3 py-2 sm:px-6 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-700">
                                        <?php echo htmlspecialchars($doc['original_filename']); ?>
                                    </td>
                                    <td class="px-3 py-2 sm:px-6 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-700">
                                        <?php echo htmlspecialchars($doc['upload_date']); ?>
                                    </td>
                                    <td class="px-3 py-2 sm:px-6 sm:py-4 whitespace-nowrap text-xs sm:text-sm font-medium flex space-x-1 sm:space-x-2">
                                        <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank"
                                           class="text-blue-600 hover:text-blue-900 transition duration-150 ease-in-out">
                                            View
                                        </a>
                                        <button type="button"
                                                onclick="showDeleteModal(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['original_filename']); ?>')"
                                                class="text-red-600 hover:text-red-900 transition duration-150 ease-in-out">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal hidden">
        <div class="modal-content">
            <h3 class="text-lg sm:text-xl font-semibold text-gray-800 mb-3 sm:mb-4">Confirm Deletion</h3>
            <p class="text-sm sm:text-base text-gray-700 mb-4 sm:mb-6">Are you sure you want to delete <span id="entryToDeleteText" class="font-bold"></span>?</p>
            <div class="flex justify-end space-x-3 sm:space-x-4">
                <button type="button" onclick="hideDeleteModal()"
                        class="py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Cancel
                </button>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="delete_document">
                    <input type="hidden" name="doc_id" id="confirmDeleteEntryId">
                    <button type="submit"
                            class="py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        const deleteModal = document.getElementById('deleteModal');
        const confirmDeleteEntryId = document.getElementById('confirmDeleteEntryId');
        const entryToDeleteText = document.getElementById('entryToDeleteText');

        function showDeleteModal(id, entryDescription) {
            confirmDeleteEntryId.value = id;
            entryToDeleteText.textContent = entryDescription;
            deleteModal.classList.remove('hidden');
        }

        function hideDeleteModal() {
            deleteModal.classList.add('hidden');
        }

        // Close modal if user clicks outside of it
        window.onclick = function(event) {
            if (event.target == deleteModal) {
                hideDeleteModal();
            }
        }
    </script>
</body>
</html>
