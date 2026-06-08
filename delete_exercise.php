<?php
require 'db_connect.php';

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    try {
        // 1. Πρώτα βρίσκουμε αν υπάρχει εικόνα για να τη διαγράψουμε από το δίσκο
        $stmt = $pdo->prepare("SELECT image_path FROM exercises WHERE id = ?");
        $stmt->execute([$id]);
        $exercise = $stmt->fetch();

        if ($exercise) {
            if ($exercise['image_path'] && file_exists('uploads/' . $exercise['image_path'])) {
                unlink('uploads/' . $exercise['image_path']);
            }

            // 2. Διαγραφή της εγγραφής από τη βάση
            $deleteStmt = $pdo->prepare("DELETE FROM exercises WHERE id = ?");
            $deleteStmt->execute([$id]);

            header("Location: view_exercises.php?msg=" . urlencode("Η άσκηση διαγράφηκε επιτυχώς!"));
            exit();
        }
    } catch (Exception $e) {
        die("Σφάλμα κατά τη διαγραφή: " . $e->getMessage());
    }
}

header("Location: view_exercises.php");
exit();