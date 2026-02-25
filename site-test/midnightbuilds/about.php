<?php
// ============================================================
// about.php — About the MidnightBuilds project
// ============================================================

$pageTitle = 'About';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container container--narrow">

        <div class="page-heading">
            <h1 class="page-title">About MidnightBuilds</h1>
        </div>

        <div class="about-content">

            <div class="about-hero-text">
                <p class="lead">
                    MidnightBuilds is a community-driven platform for sharing app ideas —
                    the kind you get at 2 AM when you can't sleep and suddenly the perfect
                    product pops into your head.
                </p>
            </div>

            <h2>Why it exists</h2>
            <p>
                Most app ideas never leave the Notes app on someone's phone. MidnightBuilds
                gives those ideas a place to live, be discovered, and be improved by community
                feedback. Whether you're a developer looking for your next side project or just
                someone who loves imagining better tools, this is your space.
            </p>

            <h2>How it works</h2>
            <ol class="about-steps">
                <li>
                    <strong>Submit</strong> — Fill in a title, a one-line pitch,
                    a full description, and a category. No account needed.
                </li>
                <li>
                    <strong>Browse</strong> — Explore all submitted ideas, filter by
                    category, or search by keyword.
                </li>
                <li>
                    <strong>Upvote</strong> — Find an idea you love? Hit the upvote
                    button so it rises to the top.
                </li>
                <li>
                    <strong>Comment</strong> — Leave feedback, suggest improvements,
                    or ask questions directly on the idea's page.
                </li>
            </ol>

            <h2>Tech stack</h2>
            <ul class="tech-list">
                <li><span class="tech-tag">PHP 8</span> Server-side logic & templating</li>
                <li><span class="tech-tag">MySQL</span> Data storage via PDO prepared statements</li>
                <li><span class="tech-tag">HTML / CSS</span> Responsive midnight-themed UI</li>
                <li><span class="tech-tag">JavaScript</span> Light progressive enhancements</li>
            </ul>

            <div class="about-cta">
                <a href="/midnightbuilds/submit.php" class="btn btn-primary">Submit Your Idea</a>
                <a href="/midnightbuilds/ideas.php"  class="btn btn-ghost">Browse Ideas</a>
            </div>

        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
