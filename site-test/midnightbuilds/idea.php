<?php
// ============================================================
// idea.php — Idea detail page
// URL: /midnightbuilds/idea.php?id=<int>
// Handles: upvote (POST ?action=upvote), comment submission
// ============================================================

require_once __DIR__ . '/includes/db.php';

$pdo = get_db();

// ---- Validate ID ----------------------------------------
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(404);
    $pageTitle = 'Not Found';
    include __DIR__ . '/includes/header.php';
    echo '<div class="container section"><div class="not-found">
            <h1>404 — Idea Not Found</h1>
            <p>That idea doesn\'t exist or the link is broken.</p>
            <a href="/midnightbuilds/ideas.php" class="btn btn-primary">Browse All Ideas</a>
          </div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

// ---- Fetch idea -----------------------------------------
$stmt = $pdo->prepare('SELECT * FROM ideas WHERE id = :id');
$stmt->execute([':id' => $id]);
$idea = $stmt->fetch();

if (!$idea) {
    http_response_code(404);
    $pageTitle = 'Not Found';
    include __DIR__ . '/includes/header.php';
    echo '<div class="container section"><div class="not-found">
            <h1>404 — Idea Not Found</h1>
            <p>We couldn\'t find an idea with that ID.</p>
            <a href="/midnightbuilds/ideas.php" class="btn btn-primary">Browse All Ideas</a>
          </div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = htmlspecialchars($idea['title']);

// ---- Handle POST (upvote or comment) --------------------
$upvoteMsg   = '';
$commentErrors = [];
$commentData   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // -- Upvote -------------------------------------------
    if (isset($_POST['action']) && $_POST['action'] === 'upvote') {
        $up = $pdo->prepare('UPDATE ideas SET upvotes = upvotes + 1 WHERE id = :id');
        $up->execute([':id' => $id]);
        $idea['upvotes']++;   // update local copy for display
        $upvoteMsg = 'Thanks for the upvote!';

    // -- Comment ------------------------------------------
    } elseif (isset($_POST['action']) && $_POST['action'] === 'comment') {
        $commentData['author'] = trim($_POST['comment_author'] ?? '');
        $commentData['body']   = trim($_POST['comment_body']   ?? '');

        if ($commentData['author'] === '') {
            $commentErrors['author'] = 'Name is required.';
        } elseif (mb_strlen($commentData['author']) > 100) {
            $commentErrors['author'] = 'Name must be 100 characters or fewer.';
        }

        if ($commentData['body'] === '') {
            $commentErrors['body'] = 'Comment cannot be empty.';
        } elseif (mb_strlen($commentData['body']) < 3) {
            $commentErrors['body'] = 'Comment is too short.';
        }

        if (empty($commentErrors)) {
            $cstmt = $pdo->prepare(
                'INSERT INTO comments (idea_id, author, body) VALUES (:idea_id, :author, :body)'
            );
            $cstmt->execute([
                ':idea_id' => $id,
                ':author'  => $commentData['author'],
                ':body'    => $commentData['body'],
            ]);
            // PRG: redirect to same page to prevent double-submit
            header('Location: /midnightbuilds/idea.php?id=' . $id . '#comments');
            exit;
        }
    }
}

// ---- Fetch comments -------------------------------------
$cStmt = $pdo->prepare(
    'SELECT author, body, created_at FROM comments WHERE idea_id = :id ORDER BY created_at ASC'
);
$cStmt->execute([':id' => $id]);
$comments = $cStmt->fetchAll();

// ---- "Just submitted" flash message ---------------------
$isNew = isset($_GET['new']) && $_GET['new'] === '1';

include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container container--narrow">

        <!-- Breadcrumb -->
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/midnightbuilds/">Home</a>
            <span>›</span>
            <a href="/midnightbuilds/ideas.php">Ideas</a>
            <span>›</span>
            <span><?= htmlspecialchars($idea['title']) ?></span>
        </nav>

        <?php if ($isNew): ?>
            <div class="alert alert-success" role="alert">
                🎉 Your idea was submitted successfully! The community can now upvote and comment on it.
            </div>
        <?php endif; ?>

        <?php if ($upvoteMsg): ?>
            <div class="alert alert-success" role="alert">
                ▲ <?= htmlspecialchars($upvoteMsg) ?>
            </div>
        <?php endif; ?>

        <!-- ── Idea card ─────────────────────────────────── -->
        <article class="idea-detail">
            <header class="idea-detail__header">
                <div class="idea-detail__meta-top">
                    <span class="category-badge category-badge--lg"><?= htmlspecialchars($idea['category']) ?></span>
                    <span class="upvote-display upvote-display--lg">▲ <?= (int) $idea['upvotes'] ?> votes</span>
                </div>
                <h1 class="idea-detail__title"><?= htmlspecialchars($idea['title']) ?></h1>
                <p class="idea-detail__pitch"><?= htmlspecialchars($idea['pitch']) ?></p>
                <div class="idea-detail__by">
                    <span>By <strong><?= htmlspecialchars($idea['author_name']) ?></strong></span>
                    <span class="separator">·</span>
                    <time datetime="<?= htmlspecialchars($idea['created_at']) ?>">
                        <?= date('F j, Y', strtotime($idea['created_at'])) ?>
                    </time>
                </div>
            </header>

            <div class="idea-detail__body">
                <?php
                // Split on double newlines to preserve paragraph breaks
                $paragraphs = array_filter(
                    explode("\n\n", $idea['description']),
                    fn($p) => trim($p) !== ''
                );
                foreach ($paragraphs as $p): ?>
                    <p><?= nl2br(htmlspecialchars(trim($p))) ?></p>
                <?php endforeach; ?>
            </div>

            <!-- ── Upvote form ───────────────────────────── -->
            <div class="upvote-section">
                <form method="POST" action="/midnightbuilds/idea.php?id=<?= $id ?>">
                    <input type="hidden" name="action" value="upvote">
                    <button type="submit" class="btn-upvote" aria-label="Upvote this idea">
                        ▲ Upvote &nbsp;<span class="upvote-count"><?= (int) $idea['upvotes'] ?></span>
                    </button>
                </form>
                <p class="upvote-hint">Found this idea interesting? Give it an upvote!</p>
            </div>
        </article>

        <!-- ── Comments ──────────────────────────────────── -->
        <section class="comments-section" id="comments">
            <h2 class="comments-title">
                Comments
                <span class="comment-count"><?= count($comments) ?></span>
            </h2>

            <?php if (empty($comments)): ?>
                <p class="empty-state">No comments yet. Be the first to share your thoughts!</p>
            <?php else: ?>
                <div class="comment-list">
                    <?php foreach ($comments as $comment): ?>
                        <div class="comment-card">
                            <div class="comment-header">
                                <strong class="comment-author"><?= htmlspecialchars($comment['author']) ?></strong>
                                <time class="comment-time">
                                    <?= date('M j, Y \a\t g:i a', strtotime($comment['created_at'])) ?>
                                </time>
                            </div>
                            <p class="comment-body"><?= nl2br(htmlspecialchars($comment['body'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- ── Comment form ──────────────────────────── -->
            <div class="comment-form-wrap">
                <h3 class="comment-form-title">Leave a Comment</h3>

                <?php if (!empty($commentErrors)): ?>
                    <div class="alert alert-error" role="alert">
                        <ul>
                            <?php foreach ($commentErrors as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form class="comment-form" method="POST"
                      action="/midnightbuilds/idea.php?id=<?= $id ?>#comments">
                    <input type="hidden" name="action" value="comment">

                    <div class="form-group <?= isset($commentErrors['author']) ? 'has-error' : '' ?>">
                        <label for="comment_author" class="form-label">Your Name <span class="required">*</span></label>
                        <input
                            type="text"
                            id="comment_author"
                            name="comment_author"
                            class="form-input"
                            maxlength="100"
                            value="<?= htmlspecialchars($commentData['author'] ?? '') ?>"
                            placeholder="Display name"
                            required
                        >
                        <?php if (isset($commentErrors['author'])): ?>
                            <span class="form-error"><?= htmlspecialchars($commentErrors['author']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group <?= isset($commentErrors['body']) ? 'has-error' : '' ?>">
                        <label for="comment_body" class="form-label">Comment <span class="required">*</span></label>
                        <textarea
                            id="comment_body"
                            name="comment_body"
                            class="form-textarea"
                            rows="4"
                            maxlength="1000"
                            placeholder="What do you think about this idea?"
                            required
                        ><?= htmlspecialchars($commentData['body'] ?? '') ?></textarea>
                        <?php if (isset($commentErrors['body'])): ?>
                            <span class="form-error"><?= htmlspecialchars($commentErrors['body']) ?></span>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm">Post Comment</button>
                </form>
            </div>
        </section>

    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
