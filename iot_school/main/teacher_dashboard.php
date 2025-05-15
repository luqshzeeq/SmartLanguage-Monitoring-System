<?php
session_start();
include 'db_connection.php'; // Database connection

// Redirect if not logged in or role is not 'teacher'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php"); // Redirect to login page if not a teacher
    exit();
}

// Get the logged-in teacher's ID
$teacher_id = $_SESSION['user_id'];

// Handle form submission for setting Language of the Day
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $language_of_the_day_input = $_POST['language_of_the_day']; // Renamed to avoid conflict

    // Update the language of the day for the teacher
    $sql = "UPDATE teacher_settings SET language_of_the_day = ? WHERE teacher_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $language_of_the_day_input, $teacher_id);

    if ($stmt->execute()) {
        $message = "Language of the Day updated successfully!";
    } else {
        $error = "Failed to update Language of the Day.";
    }
    $stmt->close();
}

// Fetch the list of available languages
$sql_languages = "SELECT * FROM languages";
$languages_result = mysqli_query($conn, $sql_languages);

// Fetch the language of the day for the logged-in teacher
$sql_teacher_settings = "SELECT language_of_the_day FROM teacher_settings WHERE teacher_id = ?";
$stmt_settings = $conn->prepare($sql_teacher_settings); // Use a different variable name for statement
$stmt_settings->bind_param("i", $teacher_id);
$stmt_settings->execute();
$result_settings = $stmt_settings->get_result();

$current_language_of_the_day = ''; // Renamed and initialized
if ($row_setting = $result_settings->fetch_assoc()) { // Use a different variable name for row
    $current_language_of_the_day = $row_setting['language_of_the_day'];
}
$stmt_settings->close();


// Fetch teacher profile details
$teacher_username = "Teacher"; // Default username
$sql_profile = "SELECT username FROM users WHERE id = ?";
$stmt_profile = $conn->prepare($sql_profile); // Use a different variable name for statement
if ($stmt_profile) { // Check if prepare() was successful
    $stmt_profile->bind_param("i", $teacher_id);
    if ($stmt_profile->execute()) {
        $result_profile = $stmt_profile->get_result();
        if ($profile_data_row = $result_profile->fetch_assoc()) { // Check if a row was fetched
            // Use htmlspecialchars to prevent XSS if displaying user-generated content
            $teacher_username = htmlspecialchars($profile_data_row['username']);
        } else {
            // Handle case where teacher profile is not found, e.g., log error or use default
            // error_log("Teacher profile not found for ID: " . $teacher_id);
        }
    } else {
        // Handle query execution error
        // error_log("Failed to execute profile query: " . $stmt_profile->error);
    }
    $stmt_profile->close();
} else {
    // Handle prepare statement error
    // error_log("Failed to prepare profile query: " . $conn->error);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
</head>
<body>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-8">
            <h1>Welcome, <?php echo $teacher_username; ?>!</h1>
            <p>Role: Teacher</p>
        </div>
        <div class="col-md-4 text-right">
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-6">
            <h4>Set Language of the Day</h4>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-group">
                    <label for="language_of_the_day">Choose Language</label>
                    <select id="language_of_the_day" name="language_of_the_day" class="form-control" required>
                        <option value="">Select Language</option>
                        <?php
                        // Reset pointer if $languages_result was used before, or re-fetch if necessary
                        // For simplicity, assuming $languages_result is fresh or mysqli_data_seek($languages_result, 0); was called
                        if ($languages_result && mysqli_num_rows($languages_result) > 0) {
                            mysqli_data_seek($languages_result, 0); // Ensure pointer is at the beginning
                            while ($lang_row = mysqli_fetch_assoc($languages_result)) { // Use a different variable name for row
                        ?>
                                <option value="<?php echo htmlspecialchars($lang_row['id']); ?>" <?php echo $current_language_of_the_day == $lang_row['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lang_row['language_name']); ?>
                                </option>
                        <?php
                            }
                        }
                        ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Set Language of the Day</button>
            </form>
            <?php if (isset($message)) { echo "<p class='text-success mt-2'>$message</p>"; } ?>
            <?php if (isset($error)) { echo "<p class='text-danger mt-2'>$error</p>"; } ?>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
</body>
</html>