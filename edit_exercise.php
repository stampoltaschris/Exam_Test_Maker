<?php
require 'db_connect.php';

$message = "";
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: view_exercises.php");
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM exercises WHERE id = ?");
    $stmt->execute([$id]);
    $exercise = $stmt->fetch();
    
    if (!$exercise) {
        header("Location: view_exercises.php");
        exit();
    }
} catch (Exception $e) {
    die("Σφάλμα ανάκτησης: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $topic = $_POST['topic'];
    $subtype = $_POST['subtype'];
    $difficulty = $_POST['difficulty'];
    $points = (int)$_POST['points'];
    $content = $_POST['content'];
    $image_path = $exercise['image_path'];
    $upload_success = true;

    if ($topic === 'Γ' || $topic === 'Δ') {
        $subtype = 'Άσκηση';
    }

    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_dir = 'uploads/';
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024;

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($_FILES['image']['tmp_name']);

        if (!in_array($mime_type, $allowed_mimes)) {
            $message = "<div style='color:red;'>Σφάλμα: Μη επιτρεπτός τύπος αρχείου.</div>";
            $upload_success = false;
        } elseif ($_FILES['image']['size'] > $max_size) {
            $message = "<div style='color:red;'>Σφάλμα: Πολύ μεγάλο αρχείο.</div>";
            $upload_success = false;
        } else {
            if ($exercise['image_path'] && file_exists($upload_dir . $exercise['image_path'])) {
                unlink($upload_dir . $exercise['image_path']);
            }
            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $file_name = bin2hex(random_bytes(8)) . '.' . $file_extension;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $file_name)) {
                $image_path = $file_name;
            } else {
                $message = "<div style='color:red;'>Σφάλμα μεταφόρτωσης.</div>";
                $upload_success = false;
            }
        }
    }

    if ($upload_success) {
        try {
            $sql = "UPDATE exercises SET topic=?, subtype=?, difficulty=?, points=?, content=?, image_path=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$topic, $subtype, $difficulty, $points, $content, $image_path, $id]);
            header("Location: view_exercises.php?msg=" . urlencode("Η άσκηση ενημερώθηκε επιτυχώς!"));
            exit();
        } catch (Exception $e) {
            $message = "<div style='color:red;'>Σφάλμα βάσης: " . $e->getMessage() . "</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Επεξεργασία Άσκησης</title>
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f4f4f4; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 600px; margin: auto; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        select, input { width: 100%; padding: 8px; margin-top: 5px; }
        button { margin-top: 20px; padding: 10px 20px; background: #28a745; color: white; border: none; cursor: pointer; border-radius: 4px; }
        #editor-container { height: 300px; background: white; margin-top: 5px; }
    </style>
</head>
<body>
<div class="container">
    <h2>Επεξεργασία Άσκησης</h2>
    <?= $message ?>
    <a href="view_exercises.php" style="text-decoration: none; font-size: 0.9em;">&larr; Επιστροφή στις ασκήσεις</a>
    <form action="" method="POST" enctype="multipart/form-data">
        <label>Θέμα:</label>
        <select name="topic" id="topic" required>
            <option value="Α" <?= $exercise['topic'] == 'Α' ? 'selected' : '' ?>>Θέμα Α</option>
            <option value="Β" <?= $exercise['topic'] == 'Β' ? 'selected' : '' ?>>Θέμα Β</option>
            <option value="Γ" <?= $exercise['topic'] == 'Γ' ? 'selected' : '' ?>>Θέμα Γ</option>
            <option value="Δ" <?= $exercise['topic'] == 'Δ' ? 'selected' : '' ?>>Θέμα Δ</option>
        </select>

        <label>Τύπος Ερώτησης:</label>
        <select name="subtype" id="subtype" required></select>

        <label>Δυσκολία:</label>
        <select name="difficulty">
            <option value="Εύκολο" <?= $exercise['difficulty'] == 'Εύκολο' ? 'selected' : '' ?>>Εύκολο</option>
            <option value="Μέτριο" <?= $exercise['difficulty'] == 'Μέτριο' ? 'selected' : '' ?>>Μέτριο</option>
            <option value="Δύσκολο" <?= $exercise['difficulty'] == 'Δύσκολο' ? 'selected' : '' ?>>Δύσκολο</option>
        </select>

        <label>Μονάδες:</label>
        <input type="number" name="points" id="pointsInput" value="<?= $exercise['points'] ?>" required>

        <label>Περιεχόμενο:</label>
        <div id="editor-container"></div>
        <input type="hidden" name="content" id="content-input">

        <label>Σχήμα/Εικόνα (Αφήστε κενό για να διατηρήσετε την υπάρχουσα):</label>
        <input type="file" name="image" accept="image/*">
        <?php if($exercise['image_path']): ?>
            <p><small>Υπάρχουσα εικόνα: <?= htmlspecialchars($exercise['image_path']) ?></small></p>
        <?php endif; ?>

        <button type="submit">Ενημέρωση Άσκησης</button>
    </form>
</div>

<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<script src="https://cdn.jsdelivr.net/npm/quill-image-resize-module@3.0.0/image-resize.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    window.Quill = Quill;
    var Align = Quill.import('attributors/style/align');
    Quill.register(Align, true);

    var quill = new Quill('#editor-container', {
        modules: {
            toolbar: [[{header:[1,2,false]}],['bold','italic','underline'],[{'align':[]}],['image','code-block','link'],[{'list':'ordered'},{'list':'bullet'}],['clean']],
            imageResize: { displaySize: true }
        },
        theme: 'snow'
    });
    quill.root.innerHTML = <?= json_encode($exercise['content']) ?>;

    var form = document.querySelector('form');
    form.onsubmit = function() {
        document.querySelector('#content-input').value = quill.root.innerHTML;
    };

    const topicSelect = document.getElementById('topic');
    const subtypeSelect = document.getElementById('subtype');
    const pointsInput = document.getElementById('pointsInput');
    const defaultSubtypes = `<option value="Σ/Λ">Σωστό / Λάθος (Α1)</option><option value="Ανάπτυξης">Ανάπτυξης / Θεωρία</option><option value="Πολλαπλής">Πολλαπλής Επιλογής</option><option value="Κενό">Συμπλήρωση Κενών</option><option value="Μετατροπή">Μετατροπή</option>`;
    const savedSubtype = <?= json_encode($exercise['subtype']) ?>;

    function updateSubtype() {
        if (topicSelect.value === 'Γ' || topicSelect.value === 'Δ') {
            subtypeSelect.innerHTML = '<option value="Άσκηση">Άσκηση</option>';
            subtypeSelect.value = 'Άσκηση';
        } else {
            let options = defaultSubtypes;
            if (topicSelect.value === 'Β') {
                options += '<option value="Κώδικας">Κώδικας</option>';
            }
            subtypeSelect.innerHTML = options;
            subtypeSelect.value = savedSubtype;
            if (!subtypeSelect.value) subtypeSelect.selectedIndex = 0;
        }
    }

    topicSelect.addEventListener('change', updateSubtype);
    subtypeSelect.addEventListener('change', function() {
        if (subtypeSelect.value === 'Σ/Λ') pointsInput.value = 2;
        else if (subtypeSelect.value === 'Άσκηση' || subtypeSelect.value === 'Κώδικας') pointsInput.value = 10;
        else pointsInput.value = 5;
    });

    updateSubtype();
});
</script>
</body>
</html>