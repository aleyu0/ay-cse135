<?php
// ============================================================
// index.php — Home page
// Shows hero section + 5 most recent ideas from the DB.
// ============================================================

require_once __DIR__ . '/includes/db.php';

$pageTitle = 'Home';

// Fetch the 5 most recent ideas
$pdo  = get_db();
$stmt = $pdo->query('SELECT id, title, pitch, category, author_name, upvotes, created_at
                     FROM ideas
                     ORDER BY created_at DESC
                     LIMIT 5');
$recentIdeas = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- ── Hero ─────────────────────────────────────────────── -->
<section class="hero">
    <div class="hero-inner">
        <span class="hero-badge">App Idea Community</span>
        <h1 class="hero-title">Build Tomorrow's Apps,<br>Tonight.</h1>
        <p class="hero-sub">
            MidnightBuilds is the place to share wild, ambitious,
            and half-baked app ideas — and discover what others are dreaming up.
        </p>
        <div class="hero-actions">
            <a href="/midnightbuilds/submit.php" class="btn btn-primary">Submit Your Idea</a>
            <a href="/midnightbuilds/ideas.php"  class="btn btn-ghost">Browse All Ideas</a>
        </div>
    </div>
</section>

<!-- ── Recent Ideas ──────────────────────────────────────── -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Recently Submitted</h2>
            <a href="/midnightbuilds/ideas.php" class="section-link">View all →</a>
        </div>

        <?php if (empty($recentIdeas)): ?>
            <p class="empty-state">No ideas yet — <a href="/midnightbuilds/submit.php">be the first!</a></p>
        <?php else: ?>
            <div class="cards-grid">
                <?php foreach ($recentIdeas as $idea): ?>
                    <article class="card">
                        <div class="card-top">
                            <span class="category-badge"><?= htmlspecialchars($idea['category']) ?></span>
                            <span class="upvote-display">▲ <?= (int) $idea['upvotes'] ?></span>
                        </div>
                        <h3 class="card-title">
                            <a href="/midnightbuilds/idea.php?id=<?= (int) $idea['id'] ?>">
                                <?= htmlspecialchars($idea['title']) ?>
                            </a>
                        </h3>
                        <p class="card-pitch"><?= htmlspecialchars($idea['pitch']) ?></p>
                        <div class="card-meta">
                            <span class="card-author">by <?= htmlspecialchars($idea['author_name']) ?></span>
                            <span class="card-date"><?= date('M j, Y', strtotime($idea['created_at'])) ?></span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ── Stats strip ───────────────────────────────────────── -->
<?php
$totalStmt = $pdo->query('SELECT COUNT(*) AS total, SUM(upvotes) AS votes FROM ideas');
$stats = $totalStmt->fetch();
?>
<section class="stats-strip">
    <div class="container stats-inner">
        <div class="stat">
            <span class="stat-number"><?= (int) $stats['total'] ?></span>
            <span class="stat-label">Ideas Shared</span>
        </div>
        <div class="stat">
            <span class="stat-number"><?= (int) ($stats['votes'] ?? 0) ?></span>
            <span class="stat-label">Total Upvotes</span>
        </div>
        <div class="stat">
            <span class="stat-number">8</span>
            <span class="stat-label">Categories</span>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
