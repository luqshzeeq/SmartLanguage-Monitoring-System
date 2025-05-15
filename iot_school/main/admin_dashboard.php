<?php
// admin_dashboard.php
session_start(); // Start the session at the very beginning

// --- AUTHENTICATION CHECK ---
// Ensure the user is logged in and has an 'admin' role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Assuming admin_dashboard.php is in a subdirectory (e.g., 'main'),
    // and login.php is in the parent directory (e.g., 'iot_school')
    header("Location: ../login.php"); 
    exit();
}

// --- DATABASE CONNECTION ---
// Assumes db_connection.php is in the SAME directory as admin_dashboard.php.
// If it's in the parent directory (e.g., iot_school/), use:
// $db_connection_path = __DIR__ . '/../db_connection.php';
// if (file_exists($db_connection_path)) { include $db_connection_path; } else { die("DB connection file not found."); }
include 'db_connection.php'; 

// --- INITIALIZE VARIABLES ---
$error_message = null;
$success_message = null;
$language_to_edit_id = null;
$language_to_edit_name = ''; // Initialize to empty string for the form value

// --- RETRIEVE AND CLEAR FLASH MESSAGES (from session) ---
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// --- HANDLE ADD LANGUAGE (POST request) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_language'])) {
    if (isset($_POST['language_name']) && !empty(trim($_POST['language_name']))) {
        $new_language_name = trim($_POST['language_name']);
        
        if (!isset($conn) || !$conn) { 
            $_SESSION['error_message'] = "Database connection error. Cannot add language.";
        } else {
            $stmt = $conn->prepare("INSERT INTO languages (language_name) VALUES (?)");
            if ($stmt) {
                $stmt->bind_param("s", $new_language_name);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Language '" . htmlspecialchars($new_language_name) . "' added successfully!";
                } else {
                    $_SESSION['error_message'] = "Error adding language: " . htmlspecialchars($stmt->error);
                }
                $stmt->close();
            } else {
                $_SESSION['error_message'] = "Error preparing statement for add: " . htmlspecialchars($conn->error);
            }
        }
    } else {
        $_SESSION['error_message'] = "Language name cannot be empty.";
    }
    header("Location: admin_dashboard.php"); 
    exit();
}

// --- HANDLE DELETE LANGUAGE (POST request for security) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_language'])) {
    if (isset($_POST['language_id_to_delete']) && filter_var($_POST['language_id_to_delete'], FILTER_VALIDATE_INT)) {
        $language_id_to_delete = (int)$_POST['language_id_to_delete'];
        
        if (!isset($conn) || !$conn) {
            $_SESSION['error_message'] = "Database connection error. Cannot delete language.";
        } else {
            $stmt = $conn->prepare("DELETE FROM languages WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $language_id_to_delete);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $_SESSION['success_message'] = "Language deleted successfully!";
                    } else {
                        $_SESSION['error_message'] = "Language not found or already deleted.";
                    }
                } else {
                    $_SESSION['error_message'] = "Error deleting language: " . htmlspecialchars($stmt->error);
                }
                $stmt->close();
            } else {
                $_SESSION['error_message'] = "Error preparing statement for delete: " . htmlspecialchars($conn->error);
            }
        }
    } else {
        $_SESSION['error_message'] = "Invalid language ID for deletion.";
    }
    header("Location: admin_dashboard.php"); 
    exit();
}

// --- HANDLE EDIT LANGUAGE - STEP 1: Fetch language data for editing (GET request) ---
if (isset($_GET['edit']) && filter_var($_GET['edit'], FILTER_VALIDATE_INT)) {
    $language_to_edit_id_from_get = (int)$_GET['edit'];
    if (!isset($conn) || !$conn) {
        $_SESSION['error_message'] = "Database connection error. Cannot fetch language for edit.";
        header("Location: admin_dashboard.php");
        exit();
    } else {
        $stmt = $conn->prepare("SELECT id, language_name FROM languages WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $language_to_edit_id_from_get);
            $stmt->execute();
            $result_edit = $stmt->get_result();
            if ($row_edit = $result_edit->fetch_assoc()) {
                $language_to_edit_id = $row_edit['id']; 
                $language_to_edit_name = $row_edit['language_name']; 
            } else {
                $_SESSION['error_message'] = "Language not found for editing (ID: " . htmlspecialchars($language_to_edit_id_from_get) . ").";
                header("Location: admin_dashboard.php");
                exit();
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "Error preparing statement for edit: " . htmlspecialchars($conn->error);
            header("Location: admin_dashboard.php");
            exit();
        }
    }
}

// --- HANDLE EDIT LANGUAGE - STEP 2: Update language data (POST request) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_language'])) {
    if (isset($_POST['language_id_to_update'], $_POST['language_name']) && 
        filter_var($_POST['language_id_to_update'], FILTER_VALIDATE_INT) && 
        !empty(trim($_POST['language_name']))) {
        
        $language_id_to_update = (int)$_POST['language_id_to_update'];
        $updated_language_name = trim($_POST['language_name']);

        if (!isset($conn) || !$conn) {
            $_SESSION['error_message'] = "Database connection error. Cannot update language.";
        } else {
            $stmt = $conn->prepare("UPDATE languages SET language_name = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("si", $updated_language_name, $language_id_to_update);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $_SESSION['success_message'] = "Language '" . htmlspecialchars($updated_language_name) . "' updated successfully!";
                    } else {
                        $_SESSION['success_message'] = "No changes made to the language or language not found.";
                    }
                } else {
                    $_SESSION['error_message'] = "Error updating language: " . htmlspecialchars($stmt->error);
                }
                $stmt->close();
            } else {
                $_SESSION['error_message'] = "Error preparing statement for update: " . htmlspecialchars($conn->error);
            }
        }
    } else {
        $_SESSION['error_message'] = "Invalid data for updating language. Ensure name is not empty.";
    }
    header("Location: admin_dashboard.php"); 
    exit();
}

// --- FETCH ALL LANGUAGES FOR DISPLAY ---
$result_all_languages = null; 
if (isset($conn) && $conn) {
    $query_all_languages = "SELECT id, language_name FROM languages ORDER BY id ASC";
    $result_all_languages = $conn->query($query_all_languages);
    if (!$result_all_languages && empty($error_message) && empty($success_message)) { 
        $error_message = "Error fetching languages: " . htmlspecialchars($conn->error);
    }
} elseif (empty($error_message) && empty($success_message)) { 
     $error_message = "Database connection not available to fetch languages.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Languages | Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #2d3748; }
        ::-webkit-scrollbar-thumb { background: #4a5568; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #718096; }
        .sidebar { transition: width 0.3s ease-in-out; }
        .btn-icon { display: inline-flex; align-items: center; justify-content: center; }
        .btn-icon i { margin-right: 0.25rem; }
        
        /* Styles for flash messages */
        .flash-message {
            /* display: block; by default, let PHP control initial visibility */
            padding: 1rem; /* Tailwind's p-4 */
            margin-bottom: 1rem; /* Tailwind's mb-4 */
            border-radius: 0.375rem; /* Tailwind's rounded-md */
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); /* Tailwind's shadow */
            transition: opacity 0.5s ease-out;
        }
        .flash-message.fade-out {
            opacity: 0;
        }
        .flash-success { /* Combined with Tailwind classes in HTML */
            background-color: #d1fae5; /* Tailwind's green-100 */
            border-left-width: 4px;
            border-color: #10b981; /* Tailwind's green-500 */
            color: #065f46; /* Tailwind's green-700 */
        }
        .flash-error { /* Combined with Tailwind classes in HTML */
             background-color: #fee2e2; /* Tailwind's red-100 */
            border-left-width: 4px;
            border-color: #ef4444; /* Tailwind's red-500 */
            color: #991b1b; /* Tailwind's red-700 */
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">

    <div class="flex h-screen overflow-hidden">
        <aside id="sidebar" class="sidebar w-64 bg-gray-800 text-gray-100 flex-shrink-0 overflow-y-auto">
            <div class="p-4">
                <a href="admin_dashboard.php" class="flex items-center space-x-2 text-white text-2xl font-semibold">
                    <i class="fas fa-shield-alt"></i> <span class="sidebar-text">Admin Panel</span>
                </a>
            </div>
            <nav class="mt-4">
                <a href="admin_dashboard.php" class="flex items-center px-4 py-3 bg-gray-700 text-white rounded-md"> 
                    <i class="fas fa-language w-6 text-center"></i> <span class="ml-3 sidebar-text">Manage Languages</span>
                </a>
                <a href="admin_users.php" class="flex items-center px-4 py-3 text-gray-300 hover:bg-gray-700 hover:text-white rounded-md">
                   <i class="fas fa-users-cog w-6 text-center"></i> <span class="ml-3 sidebar-text">Manage Users</span>
                </a> 
                <a href="logout_admin.php" class="flex items-center px-4 py-3 text-gray-300 hover:bg-gray-700 hover:text-white rounded-md">
                    <i class="fas fa-sign-out-alt w-6 text-center"></i> <span class="ml-3 sidebar-text">Logout</span>
                </a>
            </nav>
            <div class="p-4 mt-auto border-t border-gray-700">
                <button id="sidebarToggle" class="text-gray-400 hover:text-white focus:outline-none">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </div>
        </aside>

        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="bg-white shadow-md">
                <div class="container mx-auto px-6 py-3 flex items-center justify-between">
                    <div class="flex items-center">
                         <button id="mobileSidebarToggle" class="text-gray-600 focus:outline-none lg:hidden mr-4">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                        </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-gray-600 text-sm">Welcome, <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin'; ?>!</span>
                        <div class="relative">
                            <button class="block h-10 w-10 rounded-full overflow-hidden border-2 border-gray-300 focus:outline-none">
                                <img class="h-full w-full object-cover" src="https://placehold.co/40x40/718096/E2E8F0?text=A" alt="User avatar">
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
                <div class="container mx-auto">
                    <div class="mb-6">
                        <h1 class="text-3xl font-semibold text-gray-800">Language Management</h1>
                        <p class="text-gray-600">Add, edit, or delete languages for the system.</p>
                    </div>

                    <?php if ($success_message): ?>
                        <div id="successMessage" class="flash-message flash-success" role="alert">
                            <div class="flex">
                                <div class="py-1"><i class="fas fa-check-circle fa-lg mr-3"></i></div>
                                <div>
                                    <p class="font-bold">Success</p>
                                    <p class="text-sm"><?php echo htmlspecialchars($success_message); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($error_message): ?>
                        <div id="errorMessage" class="flash-message flash-error" role="alert">
                             <div class="flex">
                                <div class="py-1"><i class="fas fa-exclamation-triangle fa-lg mr-3"></i></div>
                                <div>
                                    <p class="font-bold">Error</p>
                                    <p class="text-sm"><?php echo htmlspecialchars($error_message); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="bg-white p-6 rounded-lg shadow-lg mb-6">
                        <h2 class="text-xl font-semibold text-gray-700 mb-4">
                            <?php echo $language_to_edit_id ? 'Edit Language' : 'Add New Language'; ?>
                        </h2>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-4">
                            <?php if ($language_to_edit_id): ?>
                                <input type="hidden" name="language_id_to_update" value="<?php echo htmlspecialchars($language_to_edit_id); ?>">
                            <?php endif; ?>
                            
                            <div>
                                <label for="language_name_input" class="block text-sm font-medium text-gray-700">Language Name:</label>
                                <input type="text" id="language_name_input" name="language_name" 
                                       class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                       placeholder="e.g., English, Spanish, Malay" 
                                       value="<?php echo htmlspecialchars($language_to_edit_name); ?>" required>
                            </div>
                            
                            <div class="flex items-center">
                                <?php if ($language_to_edit_id): ?>
                                    <button type="submit" name="update_language" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 btn-icon">
                                        <i class="fas fa-save"></i>Update Language
                                    </button>
                                    <a href="admin_dashboard.php" class="ml-3 inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        Cancel Edit
                                    </a>
                                <?php else: ?>
                                    <button type="submit" name="add_language" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 btn-icon">
                                        <i class="fas fa-plus-circle"></i>Add Language
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                    <div class="bg-white p-6 rounded-lg shadow-lg">
                        <h2 class="text-xl font-semibold text-gray-700 mb-4">Existing Languages</h2>
                        <?php if (isset($result_all_languages) && $result_all_languages && $result_all_languages->num_rows > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Language Name</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php while ($row = $result_all_languages->fetch_assoc()): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['id']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($row['language_name']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                                    <a href="admin_dashboard.php?edit=<?php echo htmlspecialchars($row['id']); ?>" class="text-indigo-600 hover:text-indigo-900 bg-indigo-100 hover:bg-indigo-200 px-3 py-1 rounded-md text-xs btn-icon">
                                                        <i class="fas fa-edit"></i>Edit
                                                    </a>
                                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="inline-block">
                                                        <input type="hidden" name="language_id_to_delete" value="<?php echo htmlspecialchars($row['id']); ?>">
                                                        <button type="submit" name="delete_language" 
                                                                class="text-red-600 hover:text-red-900 bg-red-100 hover:bg-red-200 px-3 py-1 rounded-md text-xs btn-icon"
                                                                onclick="return confirm('Are you sure you want to delete the language \'<?php echo htmlspecialchars(addslashes($row['language_name'])); ?>\'? This action cannot be undone.');">
                                                            <i class="fas fa-trash-alt"></i>Delete
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-box-open fa-3x text-gray-400 mb-3"></i>
                                <p class="text-gray-500">No languages found. Add some using the form above.</p>
                                <?php if ((!isset($conn) || !$conn) && empty($error_message) && empty($success_message)): ?>
                                <p class="text-red-500 mt-2">Note: Could not display languages due to a database connection issue.</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Sidebar Toggle Functionality
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        const sidebarTexts = document.querySelectorAll('.sidebar-text');

        function toggleSidebar() {
            const isCollapsed = sidebar.classList.contains('w-20');
            sidebar.classList.toggle('w-64', isCollapsed); 
            sidebar.classList.toggle('w-20', !isCollapsed); 
            
            sidebarTexts.forEach(text => {
                text.classList.toggle('hidden', !isCollapsed);
            });

            const toggleIcon = sidebarToggle.querySelector('i');
            toggleIcon.classList.toggle('fa-chevron-left', isCollapsed);
            toggleIcon.classList.toggle('fa-chevron-right', !isCollapsed);
        }

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebar);
        }
        
        if (mobileSidebarToggle) {
            mobileSidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('-translate-x-full');
                sidebar.classList.toggle('translate-x-0');
                if (!sidebar.classList.contains('-translate-x-full')) {
                    sidebar.classList.add('w-64');
                    sidebar.classList.remove('w-20');
                    sidebarTexts.forEach(text => text.classList.remove('hidden'));
                    const toggleIcon = sidebarToggle.querySelector('i');
                    toggleIcon.classList.remove('fa-chevron-right');
                    toggleIcon.classList.add('fa-chevron-left');
                }
            });
            sidebar.classList.add('fixed', 'inset-y-0', 'left-0', 'z-30', '-translate-x-full', 'lg:translate-x-0', 'lg:static', 'lg:inset-auto');
        }

        // Auto-hide flash messages
        document.addEventListener('DOMContentLoaded', (event) => {
            const successAlert = document.getElementById('successMessage'); // Changed ID to match HTML
            const errorAlert = document.getElementById('errorMessage');   // Changed ID to match HTML
            const autoHideDelay = 3000; // 3 seconds, was 4000

            function autoHide(alertElement) {
                if (alertElement) {
                    // Ensure it's visible first (in case CSS somehow hides it initially, though it shouldn't with current CSS)
                    // alertElement.style.display = 'flex'; // Or 'block' depending on your alert structure
                    
                    setTimeout(() => {
                        alertElement.classList.add('fade-out');
                        setTimeout(() => { 
                            alertElement.style.display = 'none';
                        }, 500); // Corresponds to the transition duration in CSS (0.5s)
                    }, autoHideDelay);
                }
            }
            autoHide(successAlert);
            autoHide(errorAlert);
        });
    </script>
</body>
</html>
