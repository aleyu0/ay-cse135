<?php
// ============================================================
// submit.php — Submit a new app idea
// GET  → show empty form
// POST → validate, insert, redirect to idea detail page
// ============================================================

require_once __DIR__ . '/includes/db.php';

$pageTitle = 'Submit an Idea';

// Allowed category values (server-side whitelist)
$categories = ['Productivity', 'Education', 'Gaming', 'Social',
               'Health', 'Finance', 'AI', 'Other'];

$errors   = [];   // validation error messages
$formData = [];   // repopulate form on error

// ---- Handle POST ----------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Trim all incoming fields
    $formData['title']       = trim($_POST['title']       ?? '');
    $formData['pitch']       = trim($_POST['pitch']       ?? '');
    $formData['description'] = trim($_POST['description'] ?? '');
    $formData['category']    = trim($_POST['category']    ?? '');
    $formData['author_name'] = trim($_POST['author_name'] ?? '');

    // ---- Validation -------------------------------------

    if ($formData['title'] === '') {
        $errors['title'] = 'App title is required.';
    } elseif (mb_strlen($formData['title']) > 255) {
        $errors['title'] = 'Title must be 255 characters or fewer.';
    }

    if ($formData['pitch'] === '') {
        $errors['pitch'] = 'One-line pitch is required.';
    } elseif (mb_strlen($formData['pitch']) > 255) {
        $errors['pitch'] = 'Pitch must be 255 characters or fewer.';
    }

    if ($formData['description'] === '') {
        $errors['description'] = 'Full description is required.';
    } elseif (mb_strlen($formData['description']) < 20) {
        $errors['description'] = 'Description must be at least 20 characters.';
    }

    if (!in_array($formData['category'], $categories, true)) {
        $errors['category'] = 'Please select a valid category.';
    }

    if ($formData['author_name'] === '') {
        $errors['author_name'] = 'Display name is required.';
    } elseif (mb_strlen($formData['author_name']) > 100) {
        $errors['author_name'] = 'Name must be 100 characters or fewer.';
    }

    // ---- Insert if no errors ----------------------------
    if (empty($errors)) {
        $pdo  = get_db();
        $stmt = $pdo->prepare(
            'INSERT INTO ideas (title, pitch, description, category, author_name)
             VALUES (:title, :pitch, :description, :category, :author_name)'
        );
        $stmt->execute([
            ':title'       => $formData['title'],
            ':pitch'       => $formData['pitch'],
            ':description' => $formData['description'],
            ':category'    => $formData['category'],
            ':author_name' => $formData['author_name'],
        ]);

        $newId = (int) $pdo->lastInsertId();

        // Redirect to the new idea's detail page (PRG pattern)
        header('Location: /midnightbuilds/idea.php?id=' . $newId . '&new=1');
        exit;
    }
}

include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container container--narrow">

        <div class="page-heading">
            <h1 class="page-title">Submit an Idea</h1>
            <p class="page-sub">Share your late-night app concept with the community.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error" role="alert">
                <strong>Please fix the following errors:</strong>
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form class="idea-form" method="POST" action="/midnightbuilds/submit.php" novalidate>

            <!-- App Title -->
            <div class="form-group <?= isset($errors['title']) ? 'has-error' : '' ?>">
                <label for="title" class="form-label">App Title <span class="required">*</span></label>
                <input
                    type="text"
                    id="title"
                    name="title"
                    class="form-input"
                    maxlength="255"
                    value="<?= htmlspecialchars($formData['title'] ?? '') ?>"
                    placeholder="e.g. FocusFlow"
                    required
                >
                <?php if (isset($errors['title'])): ?>
                    <span class="form-error"><?= htmlspecialchars($errors['title']) ?></span>
                <?php endif; ?>
            </div>

            <!-- One-line Pitch -->
            <div class="form-group <?= isset($errors['pitch']) ? 'has-error' : '' ?>">
                <label for="pitch" class="form-label">One-Line Pitch <span class="required">*</span></label>
                <input
                    type="text"
                    id="pitch"
                    name="pitch"
                    class="form-input"
                    maxlength="255"
                    value="<?= htmlspecialchars($formData['pitch'] ?? '') ?>"
                    placeholder="A single compelling sentence about your idea."
                    required
                >
                <?php if (isset($errors['pitch'])): ?>
                    <span class="form-error"><?= htmlspecialchars($errors['pitch']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Category -->
            <div class="form-group <?= isset($errors['category']) ? 'has-error' : '' ?>">
                <label for="category" class="form-label">Category <span class="required">*</span></label>
                <select id="category" name="category" class="form-select" required>
                    <option value="">— Select a category —</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"
                            <?= (($formData['category'] ?? '') === $cat) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['category'])): ?>
                    <span class="form-error"><?= htmlspecialchars($errors['category']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Full Description -->
            <div class="form-group <?= isset($errors['description']) ? 'has-error' : '' ?>">
                <label for="description" class="form-label">
                    Full Description <span class="required">*</span>
                    <span class="char-counter" id="desc-counter"></span>
                </label>
                <textarea
                    id="description"
                    name="description"
                    class="form-textarea"
                    rows="6"
                    maxlength="2000"
                    placeholder="Describe the problem it solves, how it works, and who it's for…"
                    required
                ><?= htmlspecialchars($formData['description'] ?? '') ?></textarea>
                <?php if (isset($errors['description'])): ?>
                    <span class="form-error"><?= htmlspecialchars($errors['description']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Author Name -->
            <div class="form-group <?= isset($errors['author_name']) ? 'has-error' : '' ?>">
                <label for="author_name" class="form-label">Your Display Name <span class="required">*</span></label>
                <input
                    type="text"
                    id="author_name"
                    name="author_name"
                    class="form-input"
                    maxlength="100"
                    value="<?= htmlspecialchars($formData['author_name'] ?? '') ?>"
                    placeholder="e.g. Alex Chen"
                    required
                >
                <?php if (isset($errors['author_name'])): ?>
                    <span class="form-error"><?= htmlspecialchars($errors['author_name']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Launch My Idea 🚀</button>
                <a href="/midnightbuilds/ideas.php" class="btn btn-ghost">Cancel</a>
            </div>

        </form>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
