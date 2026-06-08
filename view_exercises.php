<?php
require 'db_connect.php';

// Pagination settings
$defaultLimit = 10;
$allowedLimits = [5, 10, 20, 'all'];

// Get current limit from GET parameter, default to $defaultLimit
$limit = isset($_GET['limit']) && in_array($_GET['limit'], $allowedLimits) ? $_GET['limit'] : $defaultLimit;

// Get current page from GET parameter, default to 1
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Ensure page is at least 1
if ($page < 1) {
    $page = 1;
}

$selectedTopic = isset($_GET['topic']) ? $_GET['topic'] : 'all'; // Get selected topic, default to 'all'

$totalExercises = 0;
$totalPages = 1; // Default to 1 page if limit is 'all' or no exercises

try {
    $whereClause = '';
    $countParams = [];
    if ($selectedTopic !== 'all') {
        $whereClause = " WHERE topic = :topic";
        $countParams[':topic'] = $selectedTopic;
    }

    // Get total number of exercises
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM exercises" . $whereClause);
    $countStmt->execute($countParams);
    $totalExercises = $countStmt->fetchColumn();

    $sql = "SELECT * FROM exercises" . $whereClause . " ORDER BY id DESC";
    $params = $countParams; // Start with topic parameter if any

    if ($limit !== 'all') {
        $limit = (int)$limit; // Cast to int for SQL LIMIT clause
        $offset = ($page - 1) * $limit;
        if ($offset < 0) $offset = 0; // Ensure offset is not negative

        // Calculate total pages
        $totalPages = ceil($totalExercises / $limit);
        if ($totalPages == 0) $totalPages = 1; // Avoid division by zero if no exercises

        // Adjust page if it's out of bounds
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $limit; // Recalculate offset
        }

        $sql .= " LIMIT :limit OFFSET :offset";
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $exercises = $stmt->fetchAll();
} catch (Exception $e) {
    die("Σφάλμα κατά την ανάκτηση δεδομένων: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Προβολή Ασκήσεων</title>
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f4f4f4; color: #333; }
        .container { max-width: 900px; margin: auto; }
        h2 { text-align: center; color: #007bff; }
        .exercise-card { 
            background: white; 
            padding: 20px; 
            margin-bottom: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
        }
        .exercise-header { 
            border-bottom: 1px solid #eee; 
            padding-bottom: 10px; 
            margin-bottom: 10px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
        }
        .badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.85em;
            color: white;
        }
        .badge-topic { background: #6c757d; }
        .badge-points { background: #28a745; }
        .badge-edit { background: #ffc107; color: #212529 !important; text-decoration: none; transition: opacity 0.2s; }
        .badge-edit:hover { opacity: 0.8; }
        .badge-delete { background: #dc3545; text-decoration: none; transition: opacity 0.2s; }
        .badge-delete:hover { opacity: 0.8; }
        .content-box { 
            background: #fafafa; 
            padding: 15px; 
            border-left: 5px solid #007bff; 
            margin: 10px 0;
            white-space: pre-wrap; /* Διατήρηση αλλαγών γραμμής */
        }
        /* Διασφάλιση ότι οι εικόνες μέσα στο περιεχόμενο (π.χ. από Quill) 
           θα σέβονται τα όρια και το resize */
        .content-box img { 
            max-width: calc(100% - 1.27cm); 
            height: auto;
            margin-left: 1.27cm;
            display: block; /* Διασφαλίζει ότι η εσοχή εφαρμόζεται σωστά ως block στοιχείο */
        }
        .exercise-image { 
            max-width: calc(100% - 1.27cm); 
            height: auto; 
            margin-top: 10px; 
            margin-left: 1.27cm;
            border-radius: 4px; 
        }
        .no-exercises { text-align: center; padding: 50px; background: white; border-radius: 8px; }
        .nav-link { display: inline-block; margin-bottom: 20px; text-decoration: none; color: #007bff; font-weight: bold; }
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
            gap: 10px;
        }
        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #007bff;
        }
        .pagination a:hover { background-color: #f0f0f0; }
        .pagination span.current-page { background-color: #007bff; color: white; border-color: #007bff; }
        .pagination-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="container">
    <div class="top-controls">
        <a href="add_exercise.php" class="nav-link">← Προσθήκη Νέας Άσκησης</a>
        <div class="filter-sort-group">
            <form method="GET" id="filter-form" class="pagination-controls">
                <label for="topic-select" style="margin-right: 10px; margin-top: 0;">Φίλτρο Θέματος:</label>
                <select name="topic" id="topic-select" onchange="this.form.submit()">
                    <option value="all" <?= ($selectedTopic == 'all') ? 'selected' : '' ?>>Όλα τα Θέματα</option>
                    <option value="Α" <?= ($selectedTopic == 'Α') ? 'selected' : '' ?>>Θέμα Α</option>
                    <option value="Β" <?= ($selectedTopic == 'Β') ? 'selected' : '' ?>>Θέμα Β</option>
                    <option value="Γ" <?= ($selectedTopic == 'Γ') ? 'selected' : '' ?>>Θέμα Γ</option>
                    <option value="Δ" <?= ($selectedTopic == 'Δ') ? 'selected' : '' ?>>Θέμα Δ</option>
                </select>
                
                <label for="limit-select" style="margin-left: 20px; margin-right: 10px; margin-top: 0;">Ασκήσεις ανά σελίδα:</label>
                <select name="limit" id="limit-select" onchange="this.form.submit()">
                    <?php foreach ($allowedLimits as $option): ?>
                        <option value="<?= $option ?>" <?= ($limit == $option) ? 'selected' : '' ?>>
                            <?= ($option == 'all') ? 'Όλες' : $option ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php // Keep other parameters when submitting the form ?>
                <?php if (isset($_GET['page'])): ?>
                    <input type="hidden" name="page" value="<?= htmlspecialchars($_GET['page']) ?>">
                <?php endif; ?>
                <?php if (isset($_GET['topic']) && $_GET['topic'] !== 'all' && !isset($countParams[':topic'])): // Ensure topic is kept if not explicitly selected from new dropdown ?>
                    <input type="hidden" name="topic" value="<?= htmlspecialchars($_GET['topic']) ?>">
                <?php endif; ?>
            </form>
        </div>
    </div>
    <h2>Αρχείο Ασκήσεων ΑΕΠΠ</h2>

    <?php if (isset($_GET['msg'])): ?>
        <div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 20px; text-align: center;">
            <?= htmlspecialchars($_GET['msg']) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($exercises)): ?>
        <div class="no-exercises">
            <p>Δεν βρέθηκαν αποθηκευμένες ασκήσεις.</p>
        </div>
    <?php else: ?>
        <?php foreach ($exercises as $ex): ?>
            <div class="exercise-card">
                <div class="exercise-header">
                    <div>
                        <span class="badge badge-topic">Θέμα <?= htmlspecialchars($ex['topic']) ?></span>
                        <strong><?= htmlspecialchars($ex['subtype']) ?></strong>
                        <small>(<?= htmlspecialchars($ex['difficulty']) ?>)</small>
                    </div>
                    <div>
                        <span class="badge badge-points"><?= htmlspecialchars($ex['points']) ?> Μονάδες</span>
                        <a href="edit_exercise.php?id=<?= $ex['id'] ?>" class="badge badge-edit">Επεξεργασία</a>
                        <a href="delete_exercise.php?id=<?= $ex['id'] ?>" 
                           class="badge badge-delete" 
                           onclick="return confirm('Είστε σίγουροι ότι θέλετε να διαγράψετε αυτή την άσκηση;');">
                           Διαγραφή</a>
                    </div>
                </div>

                <div class="content-box">
                    <!-- Εμφάνιση περιεχομένου. Προσοχή: Εφόσον επιτρέπεται HTML, το εκτυπώνουμε απευθείας -->
                    <?= $ex['content'] ?>
                </div>

                <?php if ($ex['image_path']): ?>
                    <div>
                        <img src="uploads/<?= htmlspecialchars($ex['image_path']) ?>" alt="Σχήμα Άσκησης" class="exercise-image">
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($limit !== 'all' && $totalPages > 1): ?>
        <?php
            // Helper function to build query string while preserving current filters
            function buildPaginationUrl($page, $currentLimit, $currentTopic) {
                $params = ['page' => $page, 'limit' => $currentLimit];
                if ($currentTopic !== 'all') {
                    $params['topic'] = $currentTopic;
                }
                return '?' . http_build_query($params);
            }
        ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="<?= buildPaginationUrl($page - 1, $limit, $selectedTopic) ?>">Προηγούμενη</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current-page"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= buildPaginationUrl($i, $limit, $selectedTopic) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="<?= buildPaginationUrl($page + 1, $limit, $selectedTopic) ?>">Επόμενη</a>
            <?php endif; ?>
        </div>
    <?php elseif ($limit === 'all' && $totalExercises > 0): ?>
        <div class="pagination" style="font-weight: bold;">
            Εμφανίζονται όλες οι <?= $totalExercises ?> ασκήσεις.
        </div>
    <?php elseif ($totalExercises == 0): // No exercises found for the selected filter ?>
        <div class="pagination">
            Δεν βρέθηκαν ασκήσεις με τα επιλεγμένα κριτήρια.
        </div>
    <?php endif; ?>
</div>

</body>
</html>