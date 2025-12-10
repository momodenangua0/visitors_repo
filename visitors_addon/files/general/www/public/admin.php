<?php
require_once 'config.php';

// Start session for login management
session_start();

// Initialize login attempts tracking
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = 0;
}

function check_login_attempts() {
    if ($_SESSION['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
        $time_passed = time() - $_SESSION['last_attempt_time'];
        if ($time_passed < LOGIN_TIMEOUT) {
            $wait_time = LOGIN_TIMEOUT - $time_passed;
            die("Too many failed attempts. Please wait " . ceil($wait_time/60) . " minutes before trying again.");
        } else {
            // Reset attempts after timeout
            $_SESSION['login_attempts'] = 0;
        }
    }
}

function process_login($password) {
    global $adminPassword;
    
    // Record attempt time
    $_SESSION['last_attempt_time'] = time();
    
    // Use constant-time comparison
    if (hash_equals(hash('sha256', $adminPassword), hash('sha256', $password))) {
        $_SESSION['authenticated'] = true;
        $_SESSION['login_attempts'] = 0;
        return true;
    }
    
    $_SESSION['login_attempts']++;
    return false;
}

// Check if logged out
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.html');
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['password'])) {
    check_login_attempts();
    
    $enteredPassword = sanitize_input($_POST['password']);
    
    if (!process_login($enteredPassword)) {
        $error_message = "Incorrect password. Access denied.";
    }
}

// If authenticated, show admin panel
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    try {
        $conn = new PDO("sqlite:" . $dbfile);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $conn->prepare("SELECT * FROM visitors ORDER BY timestamp DESC LIMIT 100");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Admin Panel - Visitors Sign-in</title>
            <link rel="stylesheet" href="https://unpkg.com/@picocss/pico@latest/css/pico.min.css">
            <style>

                .container {
                    /* max-width: 1100px; */
                    margin: 40px auto;
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
                }
                .visitor-table {
                    width: 100%;
                    border-collapse: collapse;
                    border-radius: 8px;
                    overflow: hidden;
                }
                .visitor-table thead {
                    background: #007bff;
                    color: white;
                    position: sticky;
                    top: 0;
                    z-index: 10;
                }
                .visitor-table th, .visitor-table td {
                    padding: 12px;
                    text-align: left;
                    border-bottom: 1px solid #ddd;
                }
                .visitor-table tbody tr:nth-child(even) {
                    background: #f9f9f9;
                }
                .visitor-table tbody tr:hover {
                    background: #e1f5fe;
                }
                @media (max-width: 768px) {
                    .visitor-table th, .visitor-table td {
                        padding: 8px;
                        font-size: 14px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="container">
                
                <a href="?logout=1" class="contrast">Logout</a>
            </div>
            <main class="container">
                <h2>Visitor Activity</h2>
                <div style="overflow-x:auto;">
                <?php if (count($results) > 0): ?>
                    <table class="visitor-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact #</th>
                                <th>Company</th>
                                <th>Visiting</th>
                                <th>Sign-In Time</th>
                                <th>Sign-Out Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['name']) ?></td>
                                    <td><?= htmlspecialchars($row['contact']) ?></td>
                                    <td><?= htmlspecialchars($row['company']) ?></td>
                                    <td><?= htmlspecialchars($row['visiting']) ?></td>
                                    <td><?= htmlspecialchars($row['timestamp']) ?></td>
                                    <td><?= !empty($row["sign_out_timestamp"]) ? htmlspecialchars($row["sign_out_timestamp"]) : "N/A" ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <p>No visitor activity found.</p>
                    <?php endif; ?>
                </div>
            </main>
        </body>
        </html>
        <?php
    } catch(PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        echo "<p class='error'>An error occurred while fetching visitor data.</p>";
    }
} else {
    // Display login form with error handling
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Access - Visitors Sign-in</title>
        <link rel="stylesheet" href="https://unpkg.com/@picocss/pico@latest/css/pico.min.css">
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
        <style>
            :root {
                --spacing: 1rem;
                --typography-spacing-vertical: 1.5rem;
                --primary-color: #007bff;
                --text-align: center;
                --danger-color: #dc3545;
                --text-color: #333;
                --card-background: #fff;
                --border-radius: 8px;
                --table-header-bg: #007bff;
                --table-header-color: #fff;
            }

            body {
                margin: 0;
                padding: 0;
                font-family: Arial, sans-serif;
                background: var(--background-color);
                color: var(--text-color);
            }

            .container {
                width: 90%;
                max-width: 1100px;
                margin: 40px auto;
            }

            h2 {
                color: var(--primary-color);
                text-align: center;
            }

            .admin-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }

            .logout-btn {
                display: flex;
                align-items: center;
                padding: 10px 15px;
                background: var(--danger-color);
                color: white;
                border-radius: var(--border-radius);
                text-decoration: none;
                font-weight: bold;
                transition: 0.3s;
            }

            .logout-btn:hover {
                background: #c82333;
            }

            .material-icons {
                margin-right: 5px;
                vertical-align: middle;
            }

            .visitor-table {
                width: 100%;
                border-collapse: collapse;
                background: var(--card-background);
                border-radius: var(--border-radius);
                overflow: hidden;
                box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            }

            .visitor-table th {
                background: var(--table-header-bg);
                color: var(--table-header-color);
                padding: 12px;
                text-align: left;
            }

            .visitor-table td {
                padding: 10px;
                border-bottom: 1px solid #ddd;
            }

            .visitor-table tbody tr:nth-child(even) {
                background: #f9f9f9;
            }

            .visitor-table tbody tr:hover {
                background: #e1f5fe;
            }

            .login-container {
                max-width: 400px;
                margin: 80px auto;
                background: var(--card-background);
                padding: 20px;
                border-radius: var(--border-radius);
                box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            }

            input[type="password"] {
                width: 100%;
                padding: 10px;
                margin-top: 5px;
                border-radius: var(--border-radius);
                border: 1px solid #ccc;
            }

            button {
                width: 100%;
                background: var(--primary-color);
                color: white;
                padding: 12px;
                border: none;
                border-radius: var(--border-radius);
                cursor: pointer;
                font-size: 16px;
            }

            button:hover {
                background: #0056b3;
            }

            .error-message {
                color: var(--danger-color);
                background: #f8d7da;
                padding: 10px;
                border-radius: var(--border-radius);
                margin-bottom: 15px;
            }
        </style>
    </head>
    <body>
        <main class="container">
            <div class="login-container">
                <article>
                    <h2>Admin Access</h2>
                    <?php if (isset($error_message)): ?>
                        <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
                    <?php endif; ?>
                    <form method="post" autocomplete="off">
                        <div class="grid">
                            <label for="password">
                                Password
                                <input type="password" id="password" name="password" required>
                            </label>
                        </div>
                        <button type="submit" class="contrast">
                            <span class="material-icons">login</span>
                            Login
                        </button>
                    </form>
                </article>
            </div>
        </main>
    </body>
    </html>
    <?php
}
?>
