<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

$action = isset($_GET['action']) ? $_GET['action'] : '';
$method = $_SERVER['REQUEST_METHOD'];

// Helper function to check role
function check_role($required_role) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== $required_role) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
}

// ==========================================
// AUTHENTICATION
// ==========================================
if ($action === 'auth_me') {
    if (isset($_SESSION['user_id'])) {
        echo json_encode([
            'authenticated' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['name'],
                'email' => $_SESSION['email'],
                'role' => $_SESSION['role']
            ]
        ]);
    } else {
        echo json_encode(['authenticated' => false]);
    }
}
elseif ($action === 'auth_login' && $method === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Please enter both email and password.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        echo json_encode(['success' => true, 'role' => $user['role']]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'inactive'");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Your account is deactivated.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        }
    }
}
elseif ($action === 'auth_register' && $method === 'POST') {
    $name     = sanitizeInput($_POST['name']  ?? '');
    $email    = sanitizeInput($_POST['email'] ?? '');
    $phone    = sanitizeInput($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    // Optional profile fields collected during registration
    $dob           = sanitizeInput($_POST['dob']           ?? '') ?: null;
    $gender        = sanitizeInput($_POST['gender']        ?? '') ?: null;
    $category      = sanitizeInput($_POST['category']      ?? '') ?: null;
    $state         = sanitizeInput($_POST['state']         ?? '') ?: null;
    $district      = sanitizeInput($_POST['district']      ?? '') ?: null;
    $occupation    = sanitizeInput($_POST['occupation']    ?? '') ?: null;
    $annual_income = sanitizeInput($_POST['annual_income'] ?? '') ?: null;
    $education     = sanitizeInput($_POST['education']     ?? '') ?: null;

    // Basic server-side validation
    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Name, email, phone and password are required.']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
        exit;
    }
    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'Phone must be exactly 10 digits.']);
        exit;
    }
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
        exit;
    }

    // Check for duplicate email
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'This email is already registered. Please login.']);
        exit;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $role = 'citizen';

    try {
        $pdo->beginTransaction();

        // Insert user
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $hashed_password, $phone, $role]);
        $user_id = $pdo->lastInsertId();

        // Insert citizen profile with all available fields
        $stmt2 = $pdo->prepare(
            "INSERT INTO citizen_profiles (user_id, dob, gender, state, district, category, occupation, annual_income, education)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt2->execute([$user_id, $dob, $gender, $state, $district, $category, $occupation, $annual_income, $education]);

        $pdo->commit();

        // Start session
        $_SESSION['user_id'] = $user_id;
        $_SESSION['name']    = $name;
        $_SESSION['email']   = $email;
        $_SESSION['role']    = $role;

        echo json_encode(['success' => true, 'message' => 'Account created successfully!']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
    }
}
elseif ($action === 'auth_logout') {
    session_unset();
    session_destroy();
    echo json_encode(['success' => true]);
}

// ==========================================
// SCHEMES (PUBLIC)
// ==========================================
elseif ($action === 'schemes_list') {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
    $state_filter = isset($_GET['state']) ? trim($_GET['state']) : '';
    
    if (!empty($search) && isset($_SESSION['user_id']) && $_SESSION['role'] === 'citizen') {
        if (!isset($_SESSION['last_search']) || $_SESSION['last_search'] !== $search) {
            try {
                $stmt = $pdo->prepare("INSERT INTO search_history (user_id, search_keyword) VALUES (?, ?)");
                $stmt->execute([$_SESSION['user_id'], $search]);
                $_SESSION['last_search'] = $search;
            } catch (PDOException $e) {}
        }
    }

    $query = "SELECT * FROM schemes WHERE status = 'active'";
    $params = [];

    if (!empty($search)) {
        $exactTerm = "%$search%";
        $words = preg_split('/[\s,\-_]+/', $search, -1, PREG_SPLIT_NO_EMPTY);
        
        $fields = [
            'scheme_name', 'short_description', 'description', 'category', 
            'keywords', 'who_can_apply', 'eligibility', 'benefits',
            "REPLACE(keywords, '-', ' ')", "REPLACE(scheme_name, '-', ' ')"
        ];
        $fieldSql = implode(' LIKE ? OR ', $fields) . ' LIKE ?';
        $fieldCount = count($fields);

        if (count($words) <= 1) {
            $query .= " AND ($fieldSql)";
            $params = array_merge($params, array_fill(0, $fieldCount, $exactTerm));
        } else {
            $wordConditions = [];
            $wordParams = [];
            foreach ($words as $w) {
                if (mb_strlen($w) >= 2) {
                    $wTerm = "%$w%";
                    $wordConditions[] = "($fieldSql)";
                    $wordParams = array_merge($wordParams, array_fill(0, $fieldCount, $wTerm));
                }
            }

            if (!empty($wordConditions)) {
                $query .= " AND (($fieldSql) OR (" . implode(" AND ", $wordConditions) . "))";
                $params = array_merge($params, array_fill(0, $fieldCount, $exactTerm), $wordParams);
            } else {
                $query .= " AND ($fieldSql)";
                $params = array_merge($params, array_fill(0, $fieldCount, $exactTerm));
            }
        }
    }
    if (!empty($category_filter)) {
        $query .= " AND category = ?";
        $params[] = $category_filter;
    }
    if (!empty($state_filter)) {
        $query .= " AND state = ?";
        $params[] = $state_filter;
    }

    $query .= " ORDER BY created_at DESC";

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $schemes = $stmt->fetchAll();
        
        $cat_stmt = $pdo->query("SELECT DISTINCT category FROM schemes WHERE status = 'active' AND category IS NOT NULL ORDER BY category ASC");
        $categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $state_stmt = $pdo->query("SELECT DISTINCT state FROM schemes WHERE status = 'active' AND state IS NOT NULL ORDER BY CASE WHEN state = 'All India' THEN 0 ELSE 1 END, state ASC");
        $states = $state_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode(['success' => true, 'schemes' => $schemes, 'filters' => ['categories' => $categories, 'states' => $states]]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}
elseif ($action === 'schemes_top') {
    try {
        $stmt = $pdo->query("SELECT id, scheme_name, category, short_description, image FROM schemes WHERE status = 'active' ORDER BY created_at DESC LIMIT 6");
        $schemes = $stmt->fetchAll();
        echo json_encode(['success' => true, 'schemes' => $schemes]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false]);
    }
}
elseif ($action === 'schemes_get') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    try {
        $stmt = $pdo->prepare("SELECT * FROM schemes WHERE id = ? AND status = 'active'");
        $stmt->execute([$id]);
        $scheme = $stmt->fetch();
        if ($scheme) {
            echo json_encode(['success' => true, 'scheme' => $scheme]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Scheme not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching scheme']);
    }
}

// ==========================================
// PROFILE (CITIZEN ONLY)
// ==========================================
elseif ($action === 'profile_get') {
    check_role('citizen');
    try {
        $stmt = $pdo->prepare("SELECT u.name, u.email, u.phone, p.* FROM users u LEFT JOIN citizen_profiles p ON u.id = p.user_id WHERE u.id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        echo json_encode(['success' => true, 'profile' => $user]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching profile']);
    }
}
elseif ($action === 'profile_update' && $method === 'POST') {
    check_role('citizen');
    $user_id = $_SESSION['user_id'];
    $name = sanitizeInput($_POST['name'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $dob = sanitizeInput($_POST['dob'] ?? '') ?: null;
    $gender = sanitizeInput($_POST['gender'] ?? '');
    $state = sanitizeInput($_POST['state'] ?? '');
    $district = sanitizeInput($_POST['district'] ?? '');
    $category = sanitizeInput($_POST['category'] ?? '');
    $occupation = sanitizeInput($_POST['occupation'] ?? '');
    $annual_income = sanitizeInput($_POST['annual_income'] ?? '') ?: null;
    $education = sanitizeInput($_POST['education'] ?? '');

    if (empty($name) || empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Name and Phone are required.']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt1 = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
        $stmt1->execute([$name, $phone, $user_id]);

        $stmt2 = $pdo->prepare("UPDATE citizen_profiles SET dob = ?, gender = ?, state = ?, district = ?, category = ?, occupation = ?, annual_income = ?, education = ? WHERE user_id = ?");
        $stmt2->execute([$dob, $gender, $state, $district, $category, $occupation, $annual_income, $education, $user_id]);
        $pdo->commit();
        
        $_SESSION['name'] = $name;
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
    }
}
elseif ($action === 'profile_deactivate' && $method === 'POST') {
    check_role('citizen');
    try {
        $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        session_unset();
        session_destroy();
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error deactivating account']);
    }
}

// ==========================================
// CONTACT
// ==========================================
elseif ($action === 'contact_submit' && $method === 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $message = sanitizeInput($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $subject, $message]);
        echo json_encode(['success' => true, 'message' => 'Your message has been sent successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to send message. Please try again later.']);
    }
}

// ==========================================
// ADMIN ACTIONS
// ==========================================
elseif ($action === 'admin_stats') {
    check_role('admin');
    try {
        $total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'citizen'")->fetchColumn();
        $total_schemes = $pdo->query("SELECT COUNT(*) FROM schemes")->fetchColumn();
        $active_schemes = $pdo->query("SELECT COUNT(*) FROM schemes WHERE status = 'active'")->fetchColumn();
        $total_messages = $pdo->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();
        $recent_schemes = $pdo->query("SELECT scheme_name, category, status FROM schemes ORDER BY created_at DESC LIMIT 5")->fetchAll();
        $recent_users = $pdo->query("SELECT name, email, created_at FROM users WHERE role = 'citizen' ORDER BY created_at DESC LIMIT 5")->fetchAll();
        
        echo json_encode(['success' => true, 'stats' => ['total_users' => $total_users, 'total_schemes' => $total_schemes, 'active_schemes' => $active_schemes, 'total_messages' => $total_messages, 'recent_schemes' => $recent_schemes, 'recent_users' => $recent_users]]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching stats']);
    }
}
elseif ($action === 'admin_schemes') {
    check_role('admin');
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $query = "SELECT * FROM schemes";
    $params = [];
    if (!empty($search)) {
        $query .= " WHERE scheme_name LIKE ? OR category LIKE ?";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $query .= " ORDER BY created_at DESC";
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $schemes = $stmt->fetchAll();
        echo json_encode(['success' => true, 'schemes' => $schemes]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false]);
    }
}
elseif ($action === 'admin_users') {
    check_role('admin');
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $query = "SELECT * FROM users WHERE role = 'citizen'";
    $params = [];
    if (!empty($search)) {
        $query .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    $query .= " ORDER BY created_at DESC";
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        echo json_encode(['success' => true, 'users' => $users]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false]);
    }
}
elseif ($action === 'admin_messages') {
    check_role('admin');
    try {
        $stmt = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC");
        $messages = $stmt->fetchAll();
        echo json_encode(['success' => true, 'messages' => $messages]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false]);
    }
}
elseif ($action === 'admin_delete_scheme' && $method === 'POST') {
    check_role('admin');
    $id = (int)($_POST['id'] ?? 0);
    try {
        $stmt = $pdo->prepare("UPDATE schemes SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error deactivating scheme']);
    }
}
elseif ($action === 'admin_scheme_get') {
    check_role('admin');
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    try {
        $stmt = $pdo->prepare("SELECT * FROM schemes WHERE id = ?");
        $stmt->execute([$id]);
        $scheme = $stmt->fetch();
        if ($scheme) {
            echo json_encode(['success' => true, 'scheme' => $scheme]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Scheme not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching scheme']);
    }
}
elseif ($action === 'admin_scheme_save' && $method === 'POST') {
    check_role('admin');
    // Note: since this handles file upload from a form submit directly, it might not expect JSON back. 
    // Wait, the form in add-scheme.html has `action="../api.php?action=admin_scheme_save"`.
    // So we should return a redirect.
    $mode = $_POST['mode'] ?? 'add';
    $id   = (int)($_POST['id'] ?? 0);

    $scheme_name         = sanitizeInput($_POST['scheme_name'] ?? '');
    $category            = sanitizeInput($_POST['category'] ?? '');
    $state               = sanitizeInput($_POST['state'] ?? 'All India');
    $keywords            = sanitizeInput($_POST['keywords'] ?? '');
    $short_description   = sanitizeInput($_POST['short_description'] ?? '');
    $description         = sanitizeInput($_POST['description'] ?? '');
    $who_can_apply       = sanitizeInput($_POST['who_can_apply'] ?? '');
    $eligibility         = sanitizeInput($_POST['eligibility'] ?? '');
    $benefits            = sanitizeInput($_POST['benefits'] ?? '');
    $documents_required  = sanitizeInput($_POST['documents_required'] ?? '');
    $how_to_apply        = sanitizeInput($_POST['how_to_apply'] ?? '');
    $official_link       = sanitizeInput($_POST['official_application_link'] ?? '');
    $status              = sanitizeInput($_POST['status'] ?? 'active');

    if (empty($scheme_name) || empty($category) || empty($short_description) || empty($description)) {
        header('Location: ' . ($mode === 'edit' ? "admin/edit-scheme.html?id=$id&error=1" : 'admin/add-scheme.html?error=1'));
        exit;
    }

    $image_path = $_POST['existing_image'] ?? null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/schemes/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp']) && $_FILES['image']['size'] < 5000000) {
            $filename = uniqid('scheme_') . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                $image_path = 'uploads/schemes/' . $filename;
            }
        }
    }

    try {
        if ($mode === 'edit' && $id > 0) {
            $stmt = $pdo->prepare("UPDATE schemes SET scheme_name=?,category=?,state=?,keywords=?,short_description=?,description=?,who_can_apply=?,eligibility=?,benefits=?,documents_required=?,how_to_apply=?,official_application_link=?,image=?,status=? WHERE id=?");
            $stmt->execute([$scheme_name,$category,$state,$keywords,$short_description,$description,$who_can_apply,$eligibility,$benefits,$documents_required,$how_to_apply,$official_link,$image_path,$status,$id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO schemes (scheme_name,category,state,keywords,short_description,description,who_can_apply,eligibility,benefits,documents_required,how_to_apply,official_application_link,image,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$scheme_name,$category,$state,$keywords,$short_description,$description,$who_can_apply,$eligibility,$benefits,$documents_required,$how_to_apply,$official_link,$image_path,$status]);
        }
        header('Location: admin/schemes.html?success=1');
    } catch (PDOException $e) {
        header('Location: ' . ($mode === 'edit' ? "admin/edit-scheme.html?id=$id&error=1" : 'admin/add-scheme.html?error=1'));
    }
    exit;
}
else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
