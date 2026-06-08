<?php
require 'db_connect.php';

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $topic = $_POST['topic'];
    $subtype = $_POST['subtype'];
    $difficulty = $_POST['difficulty'];
    $points = (int)$_POST['points'];
    $content = $_POST['content'];

    $image_path = null;
    $upload_success = true;

    if ($topic === 'Γ' || $topic === 'Δ') {
        $subtype = 'Άσκηση';
    }

    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                $message = "<div style='color:red;'>Σφάλμα: Αδυναμία δημιουργίας καταλόγου uploads.</div>";
                $upload_success = false;
            }
        }
        
        if ($upload_success) {
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5 MB

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->file($_FILES['image']['tmp_name']);

            if (!in_array($mime_type, $allowed_mimes)) {
                $message = "<div style='color:red;'>Σφάλμα: Μη επιτρεπτός τύπος αρχείου. Επιτρέπονται μόνο JPG, PNG, GIF.</div>";
                $upload_success = false;
            } elseif ($_FILES['image']['size'] > $max_size) {
                $message = "<div style='color:red;'>Σφάλμα: Το μέγεθος του αρχείου υπερβαίνει το όριο των 5MB.</div>";
                $upload_success = false;
            } else {
                $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $file_name = bin2hex(random_bytes(8)) . '.' . $file_extension;
                $target_file = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                    $image_path = $file_name;
                } else {
                    $message = "<div style='color:red;'>Σφάλμα: Αδυναμία μεταφόρτωσης αρχείου εικόνας.</div>";
                    $upload_success = false;
                }
            }
        }
    }

    if ($upload_success) {
        try {
            $sql = "INSERT INTO exercises (topic, subtype, difficulty, points, content, image_path) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$topic, $subtype, $difficulty, $points, $content, $image_path]);
            $message = "<div style='color:green;'>Η άσκηση αποθηκεύτηκε επιτυχώς!</div>";
        } catch (Exception $e) {
            $message = "<div style='color:red;'>Σφάλμα βάσης δεδομένων: " . $e->getMessage() . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Εισαγωγή Άσκησης</title>
    <!-- Quill StyleSheet -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f4f4f4; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 600px; margin: auto; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        select, input, textarea { width: 100%; padding: 8px; margin-top: 5px; }
        button { margin-top: 20px; padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; }
        /* Ύψος του editor */
        #editor-container { height: 300px; background: white; margin-top: 5px; }
    </style>
</head>
<body>

<div class="container">
    <h2>Νέα Άσκηση ΑΕΠΠ</h2>
    <?= $message ?>
    <a href="view_exercises.php" style="text-decoration: none; font-size: 0.9em;">&larr; Επιστροφή στις ασκήσεις</a>
    <form action="" method="POST" enctype="multipart/form-data">
        <label>Θέμα:</label>
        <select name="topic" id="topic" required>
            <option value="Α">Θέμα Α</option>
            <option value="Β">Θέμα Β</option>
            <option value="Γ">Θέμα Γ</option>
            <option value="Δ">Θέμα Δ</option>
        </select>

        <label>Τύπος Ερώτησης:</label>
        <select name="subtype" id="subtype" required>
            <option value="Σ/Λ">Σωστό / Λάθος (Α1)</option>
            <option value="Ανάπτυξης">Ανάπτυξης / Θεωρία</option>
            <option value="Πολλαπλής">Πολλαπλής Επιλογής</option>
            <option value="Κενό">Συμπλήρωση Κενών</option>
            <option value="Μετατροπή">Μετατροπή</option>
        </select>

        <label>Δυσκολία:</label>
        <select name="difficulty">
            <option value="Εύκολο">Εύκολο</option>
            <option value="Μέτριο" selected>Μέτριο</option>
            <option value="Δύσκολο">Δύσκολο</option>
        </select>

        <label>Μονάδες:</label>
        <input type="number" name="points" id="pointsInput" value="5" required>

        <label>Περιεχόμενο (HTML επιτρέπεται):</label>
        <div id="editor-container"></div>
        <input type="hidden" name="content" id="content-input">

        <label>Σχήμα/Εικόνα (Προαιρετικό):</label>
        <input type="file" name="image" accept="image/*">

        <button type="submit">Αποθήκευση Άσκησης</button>
    </form>
</div>

<!-- Quill JS library -->
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<!-- Quill Image Resize Module -->
<script src="https://cdn.jsdelivr.net/npm/quill-image-resize-module@3.0.0/image-resize.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Απαραίτητο για να βρει το ImageResize module τη βιβλιοθήκη Quill
    window.Quill = Quill;

    // Ρύθμιση του Quill να χρησιμοποιεί inline styles για τη στοίχιση αντί για classes
    var Align = Quill.import('attributors/style/align');
    Quill.register(Align, true);

    // 1. Αρχικοποίηση Quill Editor
    var quill = new Quill('#editor-container', {
        modules: {
            toolbar: [
                [{ header: [1, 2, false] }],
                ['bold', 'italic', 'underline'],
                [{ 'align': [] }], // Προσθήκη κουμπιών στοίχισης
                ['image', 'code-block', 'link'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['clean']
            ],
            // Ενεργοποίηση του Image Resize Module
            imageResize: {
                displaySize: true // Εμφανίζει τις διαστάσεις κατά το resize
            }
        },
        placeholder: 'Γράψτε εδώ την εκφώνηση...',
        theme: 'snow'
    });

    // Συγχρονισμός του περιεχομένου με το κρυφό input πριν την υποβολή της φόρμας
    var form = document.querySelector('form');
    form.onsubmit = function() {
        var contentInput = document.querySelector('#content-input');
        contentInput.value = quill.root.innerHTML;
    };

    // 2. Δυναμική Λογική Φόρμας
    const topicSelect = document.getElementById('topic');
    const subtypeSelect = document.getElementById('subtype');
    const pointsInput = document.getElementById('pointsInput');
    
    // Store default options for Topic A/B
    const defaultSubtypes = `
        <option value="Σ/Λ">Σωστό / Λάθος (Α1)</option>
        <option value="Ανάπτυξης">Ανάπτυξης / Θεωρία</option>
        <option value="Πολλαπλής">Πολλαπλής Επιλογής</option>
        <option value="Κενό">Συμπλήρωση Κενών</option>
        <option value="Μετατροπή">Μετατροπή</option>
    `;

    function updatePointsBasedOnSubtype() {
        if (subtypeSelect.value === 'Σ/Λ') {
            pointsInput.value = 2;
        } else if (subtypeSelect.value === 'Άσκηση' || subtypeSelect.value === 'Κώδικας') {
            pointsInput.value = 10;
        } else {
            pointsInput.value = 5;
        }
    }

    function updateSubtypeAndPoints() {
        const currentSubtype = subtypeSelect.value;

        if (topicSelect.value === 'Γ' || topicSelect.value === 'Δ') {
            subtypeSelect.innerHTML = '<option value="Άσκηση">Άσκηση</option>';
            subtypeSelect.value = 'Άσκηση';
        } else {
            let options = defaultSubtypes;
            if (topicSelect.value === 'Β') {
                options += '<option value="Κώδικας">Κώδικας</option>';
            }
            subtypeSelect.innerHTML = options;
            // Try to restore previous selection if it exists in the new list
            if ([...subtypeSelect.options].some(o => o.value === currentSubtype)) {
                subtypeSelect.value = currentSubtype;
            }
        }
        updatePointsBasedOnSubtype();
    }

    topicSelect.addEventListener('change', updateSubtypeAndPoints);
    subtypeSelect.addEventListener('change', updatePointsBasedOnSubtype);

    updateSubtypeAndPoints();
});
</script>

</body>
</html>
