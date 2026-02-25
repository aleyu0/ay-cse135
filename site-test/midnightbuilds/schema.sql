-- ============================================================
-- MidnightBuilds — Database Schema
-- Run this file first, then seed.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS midnightbuilds
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE midnightbuilds;

-- ---- ideas table ----------------------------------------
CREATE TABLE IF NOT EXISTS ideas (
    id          INT          AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(255) NOT NULL,
    pitch       VARCHAR(255) NOT NULL,
    description TEXT         NOT NULL,
    category    VARCHAR(50)  NOT NULL,
    author_name VARCHAR(100) NOT NULL,
    upvotes     INT          DEFAULT 0,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- ---- comments table (optional feature) ------------------
CREATE TABLE IF NOT EXISTS comments (
    id         INT          AUTO_INCREMENT PRIMARY KEY,
    idea_id    INT          NOT NULL,
    author     VARCHAR(100) NOT NULL,
    body       TEXT         NOT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (idea_id) REFERENCES ideas(id) ON DELETE CASCADE
);
