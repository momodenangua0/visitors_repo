<?php
require_once 'config.php';

// Database connection using PDO
try {
    $conn = new PDO("sqlite:" . $dbfile);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit;
}

// Create tables if they do not exist
try {
    $conn->beginTransaction();

    $conn->exec("
        CREATE TABLE IF NOT EXISTS visitors (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            contact TEXT NOT NULL,
            company TEXT,
            visiting TEXT NOT NULL,
            timestamp DATETIME NOT NULL,
            sign_out_timestamp DATETIME
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS terms (
            id INTEGER PRIMARY KEY,
            term_text TEXT NOT NULL
        )
    ");

    // Check if terms table is empty
    $result = $conn->query("SELECT COUNT(*) FROM terms");
    $row = $result->fetch(PDO::FETCH_NUM);
    if ($row[0] == 0) {
        $conn->exec("
            INSERT INTO terms (term_text) VALUES
            ('I understand that as a guest, I am required to be always escorted by staff while on the premises, unless there is NO cannabis biomass on site.'),
            ('I will maintain the same high level of health and hygiene standards as staff members, including wearing gloves and other PPE items as instructed.'),
            ('I acknowledge that I will not be issued any form of security clearance such as PINs or key fobs.'),
            ('I agree that any items I bring into the facility may be subject to search before I leave the premises.'),
            ('If I am a contractor, I will bring only the minimum tools necessary to perform my job and understand that toolboxes may be subject to search.'),
            ('I will not access any areas containing cannabis biomass without an escort.'),
            ('If I need to leave a room where maintenance is being performed, I understand that I must leave with my escort and wait in a designated area.'),
            ('I agree to sign out and record the date and time of my departure before leaving the premises.'),
            ('I understand that a search of my belongings may be conducted before I depart, based on the duration of my visit, potential access to cannabis, and other relevant circumstances.'),
            ('I will comply with all instructions given by my escort and adhere to all facility rules and regulations.')
        ");
    }

    $conn->commit();
} catch(PDOException $e) {
    $conn->rollBack();
    echo "Error creating tables: " . $e->getMessage();
    exit;
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'];

    if ($action == 'get_terms') {
        $sql = "SELECT term_text FROM terms";
        $result = $conn->query($sql);

        if ($result && $row = $result->fetch(PDO::FETCH_ASSOC)) {
            do {
                echo "<label><input type='checkbox' name='terms[]' value='{$row['term_text']}' required> {$row['term_text']}</label><br>";
            } while ($row = $result->fetch(PDO::FETCH_ASSOC));
        } else {
            echo "No terms found.";
        }
    } 
    elseif ($action == 'sign_in') {
        $name = $_POST['name'];
        $contact = $_POST['contact'];
        $company = $_POST['company'];
        $visiting = $_POST['visiting'];
        $timestamp = date('Y-m-d H:i:s');

        // Using prepared statement for insert query
        $sql = "INSERT INTO visitors (name, contact, company, visiting, timestamp) 
                VALUES (:name, :contact, :company, :visiting, :timestamp)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':contact', $contact);
        $stmt->bindParam(':company', $company);
        $stmt->bindParam(':visiting', $visiting);
        $stmt->bindParam(':timestamp', $timestamp);

        try {
            $stmt->execute();
            echo "Sign-in successful!";
        } catch(PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
    }
    elseif ($action == 'search_for_sign_out') {
        try {
            $searchTerm = $_POST['searchTerm'];
            $today = date('Y-m-d');
            
            // Use prepared statement to prevent SQL injection
            $sql = "SELECT id, name, contact, timestamp FROM visitors 
                    WHERE name LIKE :searchTerm 
                    AND DATE(timestamp) = :today 
                    AND sign_out_timestamp IS NULL";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':searchTerm', '%' . $searchTerm . '%'); 
            $stmt->bindValue(':today', $today);
            $stmt->execute();
            
            $found = false;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $found = true;
                // Escape output for XSS prevention
                $id = htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8');
                $name = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
                $contact = htmlspecialchars($row['contact'], ENT_QUOTES, 'UTF-8');
                $timestamp = htmlspecialchars($row['timestamp'], ENT_QUOTES, 'UTF-8');
    
                echo "<button type='button' class='sign-out-button' data-visitor-id='{$id}'>
                        {$name} (📞 {$contact}, ⏰ {$timestamp})
                      </button><br>";
            }
            
            if (!$found) {
                echo "No matching visitors found.";
            }
        } catch(PDOException $e) {
            error_log("Search error: " . $e->getMessage());
            echo "An error occurred while searching for visitors.";
        }
    }
    elseif ($action == 'sign_out') {
        try {
            $visitorId = filter_var($_POST['visitorId'], FILTER_VALIDATE_INT);
            if ($visitorId === false) {
                throw new Exception("Invalid visitor ID");
            }
            
            $signoutTimestamp = date('Y-m-d H:i:s');
            
            // Use prepared statement for the update
            $sql = "UPDATE visitors SET sign_out_timestamp = :timestamp WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':timestamp', $signoutTimestamp);
            $stmt->bindParam(':id', $visitorId);
            
            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    echo "Sign-out successful!";
                } else {
                    echo "No visitor found with the provided ID.";
                }
            } else {
                throw new Exception("Failed to update sign-out time");
            }
        } catch(Exception $e) {
            error_log("Sign-out error: " . $e->getMessage());
            echo "An error occurred during sign-out.";
        }
    }
}
?>
