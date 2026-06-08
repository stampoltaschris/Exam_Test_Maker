<?php
require 'db_connect.php';

// Προσπάθεια φόρτωσης του PdfParser (απαιτεί composer require smalot/pdfparser)
$parser_available = false;
if (file_exists('vendor/autoload.php')) {
    require 'vendor/autoload.php';
    $parser_available = true;
} elseif (file_exists('pdfparser-master/alt_autoload.php')) {
    require 'pdfparser-master/alt_autoload.php';
    $parser_available = true;
}

$message = "";
$parsed_exercises = [];

// Χειρισμός μεμονωμένης αποθήκευσης μέσω AJAX (πριν από την υπόλοιπη επεξεργασία)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_single') {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->prepare("INSERT INTO exercises (topic, subtype, difficulty, points, content) VALUES (?, ?, 'Μέτριο', ?, ?)");
        $stmt->execute([
            $_POST['topic'],
            $_POST['subtype'],
            $_POST['points'],
            $_POST['content']
        ]);
        echo json_encode(['success' => true, 'message' => 'Η άσκηση αποθηκεύτηκε επιτυχώς!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit; // Διακοπή εκτέλεσης για AJAX requests
}

// 1. Επεξεργασία του PDF και εξαγωγή κειμένου
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['pdf_file']) && $parser_available) {
    try {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($_FILES['pdf_file']['tmp_name']);
        $text = $pdf->getText();

        // 1. Εύρεση των θέσεων όλων των θεμάτων (ΘΕΜΑ Α, Β, Γ, Δ) στο κείμενο
        // Χρησιμοποιούμε preg_match_all για να βρούμε τα ακριβή byte offsets κάθε εμφάνισης.
        // Η regex είναι "χαλαρή" για να πιάνει κενά (Θ Ε Μ Α) και λατινικούς χαρακτήρες (A, B, D).
        $pattern = '/Θ\s*Ε\s*Μ\s*Α\s*[\s\.\-:]?\s*([ΑAΒBΓΔD])(?=[\s\.\-\:\d]|$)/ui';
        preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);

        $topic_parts = [];
        if (!empty($matches[0])) {
            for ($i = 0; $i < count($matches[0]); $i++) {
                $start = $matches[0][$i][1];
                $end = isset($matches[0][$i + 1]) ? $matches[0][$i + 1][1] : strlen($text);
                $topic_parts[] = substr($text, $start, $end - $start);
            }
        }

        foreach ($topic_parts as $part) {
            if (preg_match('/Θ\s*Ε\s*Μ\s*Α\s*[\s\.\-:]?\s*([ΑAΒBΓΔD])/ui', $part, $header_matches)) {
                $current_topic = mb_strtoupper($header_matches[1], 'UTF-8');
                // Μετατροπή πιθανών Λατινικών χαρακτήρων σε Ελληνικούς
                $current_topic = strtr($current_topic, ['A' => 'Α', 'B' => 'Β', 'D' => 'Δ']);
                
                if ($current_topic === 'Γ' || $current_topic === 'Δ') {
                    // Αφαίρεση της κεφαλίδας "ΘΕΜΑ Χ" από την αρχή του κειμένου
                    $content = preg_replace('/^Θ\s*Ε\s*Μ\s*Α\s*[\s\.\-:]?\s*[ΑAΒBΓΔD]\s*/ui', '', trim($part), 1);
                    if (strlen($content) < 20) continue;

                    $parsed_exercises[] = [
                        'topic' => $current_topic,
                        'content' => $content,
                        'points' => 25, // Τα θέματα Γ και Δ έχουν πάντα συνολικά 25 μονάδες
                        'subtype' => 'Άσκηση'
                    ];
                } else {
                    // Αφαίρεση της κεφαλίδας "ΘΕΜΑ Χ"
                    $content_after_topic_header = preg_replace('/^Θ\s*Ε\s*Μ\s*Α\s*[\s\.\-:]?\s*[ΑAΒBΓΔD]\s*[\s\.\-\:]*/ui', '', $part, 1);
                    
                    // Διαχωρισμός σε υπο-ερωτήματα (Α1, Α2...) με χρήση lookahead για διατήρηση του διαχωριστή
                    $sub_parts = preg_split('/(?=[ΑΒ]\d+\.)/u', $content_after_topic_header, -1, PREG_SPLIT_NO_EMPTY);

                    foreach ($sub_parts as $sub_content) {
                        $sub_content = trim($sub_content);
                        if (strlen($sub_content) < 10) continue;

                        // Pattern για το εισαγωγικό κείμενο Σωστό/Λάθος
                        $tf_pattern = '/(Να\s+γράψετε\s+στο\s+τετράδιό\s+σας\s+τον\s+αριθμό.*?λανθασμένη)/uis';
                        
                        if (preg_match($tf_pattern, $sub_content, $tf_match)) {
                            $intro = $tf_match[0];
                            // Καθαρισμός κειμένου από το intro και το πρόθεμα (π.χ. Α1.)
                            $cleaned_block = str_replace($intro, '', $sub_content);
                            $cleaned_block = preg_replace('/^[Α-Β]\d+\.\s*/u', '', trim($cleaned_block), 1);
                            
                            // Διαχωρισμός των προτάσεων 1-5 ή 1-10
                            $statements = preg_split('/(\d+\s*\.)/u', $cleaned_block, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                            
                            $current_num = '';
                            foreach ($statements as $s_part) {
                                if (preg_match('/^\d+\s*\.$/u', $s_part)) {
                                    $current_num = trim($s_part);
                                } else {
                                    $full_stmt = trim($current_num . " " . $s_part);
                                    if (strlen($full_stmt) > 10) {
                                        $parsed_exercises[] = [
                                            'topic' => $current_topic,
                                            'content' => $full_stmt,
                                            'points' => 2,
                                            'subtype' => 'Σ/Λ'
                                        ];
                                    }
                                }
                            }
                        } else {
                            $parsed_exercises[] = [
                                'topic' => $current_topic,
                                'content' => $sub_content,
                                // Αναζήτηση της λέξης "Μονάδες" ακολουθούμενης από αριθμό (π.χ. Μονάδες 5)
                                'points' => preg_match('/\bΜονάδες\s+(\d+)/u', $sub_content, $pt) ? (int)$pt[1] : 5,
                                'subtype' => 'Ανάπτυξης'
                            ];
                        }
                    }
                }
            }
        }
        $message = "<div style='color:blue;'>Το PDF αναλύθηκε. Παρακαλώ ελέγξτε τα δεδομένα παρακάτω.</div>";
    } catch (Exception $e) {
        $message = "<div style='color:red;'>Σφάλμα: " . $e->getMessage() . "</div>";
    }
}

// 2. Τελική αποθήκευση στη βάση μετά την επιβεβαίωση του χρήστη
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_import'])) {
    $topics = $_POST['topic'];
    $contents = $_POST['content'];
    $points = $_POST['points'];
    $subtypes = $_POST['subtype'];

    try {
        $stmt = $pdo->prepare("INSERT INTO exercises (topic, subtype, difficulty, points, content) VALUES (?, ?, 'Μέτριο', ?, ?)");
        $count = 0;
        for ($i = 0; $i < count($contents); $i++) {
            if (!empty(trim($contents[$i]))) {
                $stmt->execute([$topics[$i], $subtypes[$i], $points[$i], $contents[$i]]);
                $count++;
            }
        }
        $message = "<div style='color:green;'>Επιτυχής εισαγωγή $count ασκήσεων!</div>";
        $parsed_exercises = []; // Καθαρισμός μετά την αποθήκευση
    } catch (Exception $e) {
        $message = "<div style='color:red;'>Σφάλμα βάσης: " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Εισαγωγή από PDF</title>
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f4f4f4; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 800px; margin: auto; }
        .info { background: #e7f3ff; padding: 10px; border-radius: 4px; margin-bottom: 20px; font-size: 0.9em; }
        .preview-card { border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; border-radius: 5px; background: #fff; }
        textarea { width: 100%; height: 80px; margin-top: 5px; }
        .grid { display: flex; gap: 10px; margin-bottom: 5px; }
        .grid div { flex: 1; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Τοπική Εισαγωγή Θεμάτων από PDF</h2>
        <?= $message ?>
        
        <?php if (!$parser_available): ?>
            <div style="color:red; font-weight:bold;">
                Προσοχή: Η βιβλιοθήκη PdfParser δεν βρέθηκε. <br>
                Τρέξτε την εντολή: <code>composer require smalot/pdfparser</code>
            </div>
        <?php endif; ?>

        <div class="info">
            <strong>Οδηγίες:</strong> Ανεβάστε το PDF. Το σύστημα θα προσπαθήσει να χωρίσει τις ερωτήσεις χρησιμοποιώντας Regex.
            Μετά την ανάλυση, μπορείτε να διορθώσετε το κείμενο πριν την αποθήκευση.
        </div>

        <form action="" method="POST" enctype="multipart/form-data">
            <label>Επιλέξτε το PDF των εξετάσεων:</label>
            <input type="file" name="pdf_file" accept=".pdf" required style="display:block; margin: 20px 0;">
            
            <button type="submit" style="padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; border-radius: 4px;">
                Ανάλυση PDF
            </button>
        </form>

        <?php if (!empty($parsed_exercises)): ?>
            <hr>
            <h3>Προεπισκόπηση Δεδομένων</h3>
            <form action="" method="POST">
                <?php foreach ($parsed_exercises as $index => $ex): ?>
                    <div class="preview-card" id="card-<?= $index ?>">
                        <div class="grid">
                            <div>
                                <label>Θέμα:</label>
                                <input type="text" name="topic[]" class="ex-topic" value="<?= htmlspecialchars($ex['topic']) ?>" style="width:40px;" readonly>
                            </div>
                            <div>
                                <label>Τύπος:</label>
                                <select name="subtype[]" class="ex-subtype">
                                    <?php
                                    $current_topic = $ex['topic'];
                                    $current_subtype = $ex['subtype'];

                                    $subtypes_options = [];
                                    if ($current_topic === 'Γ' || $current_topic === 'Δ') {
                                        $subtypes_options = ['Άσκηση'];
                                    } else {
                                        $subtypes_options = ['Σ/Λ', 'Ανάπτυξης', 'Πολλαπλής', 'Κενό', 'Μετατροπή'];
                                        if ($current_topic === 'Β') {
                                            $subtypes_options[] = 'Κώδικας';
                                        }
                                    }

                                    foreach ($subtypes_options as $option) {
                                        $selected = ($option === $current_subtype) ? 'selected' : '';
                                        echo "<option value=\"{$option}\" {$selected}>{$option}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div>
                                <label>Μονάδες:</label>
                                <input type="number" name="points[]" class="ex-points" value="<?= $ex['points'] ?>" style="width:60px;">
                            </div>
                        </div>
                        <label>Περιεχόμενο:</label>
                        <textarea name="content[]" class="ex-content"><?= htmlspecialchars($ex['content']) ?></textarea>
                        <button type="button" onclick="saveSingle(<?= $index ?>)" class="save-btn" style="margin-top: 10px; background: #17a2b8; color: white; border: none; padding: 8px 15px; cursor: pointer; border-radius: 4px; font-weight: bold;">
                            Επιβεβαίωση και Αποθήκευση Ερώτησης
                        </button>
                    </div>
                <?php endforeach; ?>
                
                <button type="submit" name="confirm_import" style="padding: 15px 30px; background: #28a745; color: white; border: none; cursor: pointer; border-radius: 4px; width: 100%;">
                    Επιβεβαίωση και Αποθήκευση Όλων
                </button>
            </form>
        <?php endif; ?>
        
        <br>
        <a href="view_exercises.php">← Επιστροφή στις ασκήσεις</a>
    </div>

    <script>
    function saveSingle(index) {
        const card = document.getElementById('card-' + index);
        const btn = card.querySelector('.save-btn');
        
        // Συλλογή δεδομένων από το συγκεκριμένο card
        const data = new FormData();
        data.append('action', 'save_single');
        data.append('topic', card.querySelector('.ex-topic').value);
        data.append('subtype', card.querySelector('.ex-subtype').value);
        data.append('points', card.querySelector('.ex-points').value);
        data.append('content', card.querySelector('.ex-content').value);

        // Οπτική ένδειξη φόρτωσης
        btn.disabled = true;
        btn.innerText = 'Αποθήκευση...';

        fetch(window.location.href, {
            method: 'POST',
            body: data
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                // Επιτυχία: Αλλαγή εμφάνισης του card
                card.style.border = '2px solid #28a745';
                card.style.backgroundColor = '#f8fff8';
                btn.innerText = 'Αποθηκεύτηκε!';
                btn.style.background = '#28a745';
            } else {
                alert('Σφάλμα: ' + result.message);
                btn.disabled = false;
                btn.innerText = 'Επιβεβαίωση και Αποθήκευση Ερώτησης';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Παρουσιάστηκε σφάλμα κατά την επικοινωνία με το διακομιστή.');
            btn.disabled = false;
        });
    }
    </script>
</body>
</html>