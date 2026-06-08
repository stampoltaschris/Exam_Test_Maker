<?php
require 'db_connect.php';

/**
 * Συνάρτηση για την επιλογή τυχαίων ασκήσεων που συμπληρώνουν ένα συγκεκριμένο άθροισμα πόντων.
 */
function pickExercisesByPoints($pool, $targetPoints) {
    shuffle($pool);
    $selected = [];
    $currentSum = 0;

    foreach ($pool as $ex) {
        if ($currentSum + $ex['points'] <= $targetPoints) {
            $selected[] = $ex;
            $currentSum += $ex['points'];
        }
        if ($currentSum == $targetPoints) break;
    }

    return ($currentSum == $targetPoints) ? $selected : false;
}

$test = [
    'Α' => [],
    'Β' => [],
    'Γ' => [],
    'Δ' => []
];

$error_message = "";

try {
    // --- ΘΕΜΑ Α ---
    // 1. Παίρνουμε 5 Σωστό/Λάθος (Α1) - Θεωρούμε 2 μονάδες η καθεμία = 10 μονάδες
    $stmt = $pdo->prepare("SELECT * FROM exercises WHERE topic = 'Α' AND subtype = 'Σ/Λ' ORDER BY RAND() LIMIT 5");
    $stmt->execute();
    $a1 = $stmt->fetchAll();

    if (count($a1) < 5) {
        throw new Exception("Δεν υπάρχουν αρκετές ερωτήσεις Σ/Λ για το Θέμα Α1.");
    }
    $test['Α']['Α1'] = $a1;

    // 2. Συμπλήρωση υπολοίπου (15 μονάδες) για το Θέμα Α
    $stmt = $pdo->prepare("SELECT * FROM exercises WHERE topic = 'Α' AND subtype != 'Σ/Λ'");
    $stmt->execute();
    $poolA = $stmt->fetchAll();
    $remainingA = pickExercisesByPoints($poolA, 15);
    if (!$remainingA) throw new Exception("Αδυναμία συμπλήρωσης 25 μονάδων για το Θέμα Α.");
    $test['Α']['others'] = $remainingA;

    // --- ΘΕΜΑ Β --- (25 μονάδες)
    $stmt = $pdo->prepare("SELECT * FROM exercises WHERE topic = 'Β'");
    $stmt->execute();
    $poolB = $stmt->fetchAll();
    $selectedB = pickExercisesByPoints($poolB, 25);
    if (!$selectedB) throw new Exception("Αδυναμία συμπλήρωσης 25 μονάδων για το Θέμα Β.");
    $test['Β'] = $selectedB;

    // --- ΘΕΜΑ Γ --- (25 μονάδες)
    $stmt = $pdo->prepare("SELECT * FROM exercises WHERE topic = 'Γ' AND points = 25 ORDER BY RAND() LIMIT 1");
    $stmt->execute();
    $test['Γ'] = $stmt->fetch();
    if (!$test['Γ']) throw new Exception("Δεν βρέθηκε άσκηση 25 μονάδων για το Θέμα Γ.");

    // --- ΘΕΜΑ Δ --- (25 μονάδες)
    $stmt = $pdo->prepare("SELECT * FROM exercises WHERE topic = 'Δ' AND points = 25 ORDER BY RAND() LIMIT 1");
    $stmt->execute();
    $test['Δ'] = $stmt->fetch();
    if (!$test['Δ']) throw new Exception("Δεν βρέθηκε άσκηση 25 μονάδων για το Θέμα Δ.");

} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Παραγωγή Διαγωνίσματος</title>
    <style>
        body { font-family: "Times New Roman", Times, serif; line-height: 1.6; margin: 40px; background: #f0f0f0; }
        .paper { background: white; padding: 50px; max-width: 850px; margin: auto; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1, h2 { text-align: center; text-transform: uppercase; }
        .topic-header { background: #eee; padding: 5px 10px; font-weight: bold; border: 1px solid #000; margin-top: 30px; }
        .exercise-item { margin-bottom: 20px; }
        .points { font-style: italic; float: right; }
        .content { margin-top: 5px; white-space: pre-wrap; }
        .no-print { text-align: center; margin-bottom: 20px; }
        img { max-width: 100%; height: auto; display: block; margin: 10px 0; }
        
        @media print {
            body { background: white; margin: 0; }
            .paper { box-shadow: none; width: 100%; max-width: none; padding: 20px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.location.reload();">Νέο Τυχαίο Διαγώνισμα</button>
    <button onclick="window.print();">Εκτύπωση (PDF)</button>
    <br><br>
    <a href="view_exercises.php">Επιστροφή στο Αρχείο</a>
</div>

<div class="paper">
    <h1>ΔΙΑΓΩΝΙΣΜΑ ΠΡΟΣΟΜΟΙΩΣΗΣ</h1>
    <h2>ΑΝΑΠΤΥΞΗ ΕΦΑΡΜΟΓΩΝ ΣΕ ΠΡΟΓΡΑΜΜΑΤΙΣΤΙΚΟ ΠΕΡΙΒΑΛΛΟΝ</h2>
    <hr>

    <?php if ($error_message): ?>
        <div style="color:red; text-align:center; font-weight:bold;">
            Σφάλμα: <?= $error_message ?><br>
            <small>Βεβαιωθείτε ότι έχετε αρκετές ασκήσεις στη βάση με τα σωστά μόρια (π.χ. 25άρια για Γ και Δ).</small>
        </div>
    <?php else: ?>

        <!-- ΘΕΜΑ Α -->
        <div class="topic-header">ΘΕΜΑ Α</div>
        
        <div class="exercise-item">
            <strong>Α1.</strong> Να γράψετε στο τετράδιό σας τον αριθμό καθεμιάς από τις παρακάτω προτάσεις 1-5 και, δίπλα, τη λέξη ΣΩΣΤΟ, αν η πρόταση είναι σωστή, ή τη λέξη ΛΑΘΟΣ, αν η πρόταση είναι λανθασμένη.
            <div class="points">Μονάδες 10</div>
            <ol>
                <?php foreach ($test['Α']['Α1'] as $ex): ?>
                    <li><?= $ex['content'] ?></li>
                <?php endforeach; ?>
            </ol>
        </div>

        <?php 
        $counter = 2;
        foreach ($test['Α']['others'] as $ex): ?>
            <div class="exercise-item">
                <strong>Α<?= $counter++ ?>.</strong>
                <span class="points">Μονάδες <?= $ex['points'] ?></span>
                <div class="content"><?= $ex['content'] ?></div>
                <?php if ($ex['image_path']): ?>
                    <img src="uploads/<?= $ex['image_path'] ?>">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <!-- ΘΕΜΑ Β -->
        <div class="topic-header">ΘΕΜΑ Β</div>
        <?php 
        $counter = 1;
        foreach ($test['Β'] as $ex): ?>
            <div class="exercise-item">
                <strong>Β<?= $counter++ ?>.</strong>
                <span class="points">Μονάδες <?= $ex['points'] ?></span>
                <div class="content"><?= $ex['content'] ?></div>
                <?php if ($ex['image_path']): ?>
                    <img src="uploads/<?= $ex['image_path'] ?>">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <!-- ΘΕΜΑ Γ -->
        <div class="topic-header">ΘΕΜΑ Γ</div>
        <div class="exercise-item">
            <span class="points">Μονάδες <?= $test['Γ']['points'] ?></span>
            <div class="content"><?= $test['Γ']['content'] ?></div>
            <?php if ($test['Γ']['image_path']): ?>
                <img src="uploads/<?= $test['Γ']['image_path'] ?>">
            <?php endif; ?>
        </div>

        <!-- ΘΕΜΑ Δ -->
        <div class="topic-header">ΘΕΜΑ Δ</div>
        <div class="exercise-item">
            <span class="points">Μονάδες <?= $test['Δ']['points'] ?></span>
            <div class="content"><?= $test['Δ']['content'] ?></div>
            <?php if ($test['Δ']['image_path']): ?>
                <img src="uploads/<?= $test['Δ']['image_path'] ?>">
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 50px; font-weight: bold;">
            --- ΚΑΛΗ ΕΠΙΤΥΧΙΑ ---
        </div>

    <?php endif; ?>
</div>

</body>
</html>