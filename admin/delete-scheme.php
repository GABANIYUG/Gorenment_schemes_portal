<?php
require_once '../includes/admin-auth.php';
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    
    try {
        // Soft delete: set status to inactive
        $stmt = $pdo->prepare("UPDATE schemes SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$id]);
        
        header("Location: schemes.php?success=1");
        exit();
    } catch (PDOException $e) {
        die("Error deactivating scheme.");
    }
} else {
    header("Location: schemes.php");
    exit();
}
?>
