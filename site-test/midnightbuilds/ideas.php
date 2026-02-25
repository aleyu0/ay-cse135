<?php
// ============================================================
// ideas.php — Browse all ideas with search + category filter
// Query params: ?search=keyword  ?category=Gaming
// ============================================================

require_once __DIR__ . '/includes/db.php';

$pageTitle = 'Browse Ideas';

$pdo = get_db();

// ---- Allowed categories (whitelist for filter) -----------
$categories = ['Productivity', 'Education', 'Gaming', 'Social',
               'Health', 'Finance', 'AI', 'Other'];

// ---- Read & sanitize GET params -------------------------
$search   = trim($_GET['search']   ?? '');
$category = trim($_GET['category'] ?? '');

// Validate category against whitelist
if ($category && !in_array($category, $categories, true)) {
    $category = '';
}

// ---- Build query dynamically with prepared statements ---
$conditions = [];
$params     = [];

if ($search !== '') {
    // Search across title and pitch
    $conditions[] = '(title LIKE :search OR pitch LIKE :search OR description LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

if ($category !== '') {
    $conditions[] = 'category = :category';
    $params[':category'] = $category;
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$sql   = "SELECT id, title, pitch, category, author_name, upvotes, created_at
          FROM ideas
          $where
          ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ideas = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">

        <div class="page-heading">
            <h1 class="page-title">Browse Ideas</h1>
            <p class="page-sub">Discover app concepts from the MidnightBuilds community.</p>
        </div>

        <!-- ── Filters ──────────────────────────────────── -->
        <form class="filter-bar" method="GET" action="/midnightbuilds/ideas.php">
            <div class="filter-group">
                <input
                    type="search"
                    name="search"
                    class="filter-input"
                    placeholder="Search ideas…"
                    value="<?= htmlspecialchars($search) ?>"
                    aria-label="Search ideas"
                >
            </div>
            <div class="filter-group">
                <select name="category" class="filter-select" aria-label="Filter by category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"
                            <?= ($category === $cat) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <?php if ($search || $category): ?>
                <a href="/midnightbuilds/ideas.php" class="btn btn-ghost btn-sm">Clear</a>
            <?php endif; ?>
        </form>

        <!-- ── Result count ─────────────────────────────── -->
        <p class="result-count">
            <?php
            $count = count($ideas);
            echo $count === 1 ? '1 idea found' : "$count ideas found";
            if ($search)   echo ' for <strong>' . htmlspecialchars($search) . '</strong>';
            if ($category) echo ' in <strong>' . htmlspecialchars($category) . '</strong>';
            ?>
        </p>

        <!-- ── Ideas list ───────────────────────────────── -->
        <?php if (empty($ideas)): ?>
            <div class="empty-state-box">
                <p>No ideas match your search.</p>
                <a href="/midnightbuilds/submit.php" class="btn btn-primary btn-sm">Submit the first one</a>
            </div>
        <?php else: ?>
            <div class="cards-grid">
                <?php foreach ($ideas as $idea): ?>
                    <article class="card">
                        <div class="card-top">
                            <span class="category-badge"><?= htmlspecialchars($idea['category']) ?></span>
                            <span class="upvote-display">▲ <?= (int) $idea['upvotes'] ?></span>
                        </div>
                        <h2 class="card-title">
                            <a href="/midnightbuilds/idea.php?id=<?= (int) $idea['id'] ?>">
                                <?= htmlspecialchars($idea['title']) ?>
                            </a>
                        </h2>
                        <p class="card-pitch"><?= htmlspecialchars($idea['pitch']) ?></p>
                        <div class="card-meta">
                            <span class="card-author">by <?= htmlspecialchars($idea['author_name']) ?></span>
                            <span class="card-date"><?= date('M j, Y', strtotime($idea['created_at'])) ?></span>
                        </div>
                        <a href="/midnightbuilds/idea.php?id=<?= (int) $idea['id'] ?>"
                           class="card-link">Read more →</a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
