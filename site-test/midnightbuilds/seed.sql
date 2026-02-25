-- ============================================================
-- MidnightBuilds — Seed Data
-- Run AFTER schema.sql
-- ============================================================

USE midnightbuilds;

INSERT INTO ideas (title, pitch, description, category, author_name, upvotes) VALUES

('FocusFlow',
 'A distraction-blocking timer that adapts to your work rhythm.',
 'FocusFlow combines the Pomodoro technique with machine-learning-based distraction detection. The app monitors which apps you switch to during focus sessions and automatically blocks the most common offenders after two slips. It also generates a daily focus-score report so you can track improvement over time. A built-in soundscape engine plays ambient noise tuned to your most productive sessions.',
 'Productivity', 'Alessio Yu', 12),

('CampusConnect',
 'A hyperlocal social hub exclusively for college campuses.',
 'CampusConnect creates private, verified communities for each university where students can discover clubs, find study partners, trade textbooks, and post campus events. Verification is done via .edu email, ensuring the community stays tight-knit. A built-in anonymous "hot-take" feed lets students voice opinions without social pressure, while a curated events calendar surfaces what is happening on campus this week.',
 'Social', 'Maria Santos', 27),

('NutriQuest',
 'Turn healthy eating into a daily RPG adventure.',
 'NutriQuest gamifies meal logging by assigning XP and loot to nutritious choices. Log a balanced meal and your avatar levels up; eat junk food and face in-game penalties. Weekly dungeon raids require the whole family to hit nutrition goals together, fostering accountability. An AI chef suggests recipes based on what is already in your fridge, helping reduce food waste while keeping your character strong.',
 'Health', 'Devon Park', 8),

('PitchDeck AI',
 'Generate investor-ready slide decks from a one-paragraph idea.',
 'Paste a rough description of your startup and PitchDeck AI produces a ten-slide investor deck complete with market-size estimates, a business-model canvas, and a competitor landscape pulled from live data. Each slide includes speaker notes and a "weak point" flag where the AI believes an investor might push back, so founders can prepare counter-arguments before the meeting.',
 'AI', 'Jordan Lee', 41),

('PixelPal',
 'A retro-pixel companion game that teaches kids coding basics.',
 'PixelPal puts kids in charge of a cute pixel-art character that can only perform actions the child programs through a drag-and-drop block interface. Puzzles start with simple move commands and gradually introduce loops, conditionals, and functions. Progress unlocks new character skins and worlds. Parents receive weekly reports showing which coding concepts their child has mastered, mapped to CS education standards.',
 'Education', 'Sam Rivera', 19);
