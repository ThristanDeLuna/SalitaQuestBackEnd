-- Salita Quest: starter data (Member B)
-- Run in Navicat AFTER migrations 010 and 011 have created `regions` and `words`.
-- Safe to run more than once: INSERT IGNORE skips rows that already exist
-- (regions.code and (words.region_code, words.word) are unique).

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1. REGIONS (keep). Pin positions are one well-known city per language,
--    approximate; check each pin on the map.
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO regions (code, name, area, description, latitude, longitude) VALUES
('tagalog',    'Tagalog',          'Luzon',           'Spoken in Metro Manila and the provinces around it. Filipino is based on Tagalog.',  14.599500, 120.984200),
('cebuano',    'Cebuano (Bisaya)', 'Central Visayas', 'Spoken in Cebu, Bohol and nearby islands, and widely in parts of Mindanao.',          10.315700, 123.885400),
('hiligaynon', 'Hiligaynon',       'Western Visayas', 'Also called Ilonggo. Spoken in Iloilo, Guimaras and Negros Occidental.',            10.720200, 122.562100);

-- ---------------------------------------------------------------------------
-- 2. DRAFT WORDS (placeholders, NOT verified by speakers).
--    Purpose: lets C (games) and D (approvals) work against real rows now.
--    * Tagalog rows are copied from the team's mockup (word list page).
--    * Cebuano and Hiligaynon rows are my best draft of the same five concepts.
--      A native speaker MUST check every word and sentence before the defense.
--    Replace them later through the admin Words page (or edit this file).
--    Each region has 5 words with DIFFERENT translations, so the matching game
--    (needs 4+) works and the listening quiz (needs 5 with audio) can work once
--    audio is uploaded.
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO words (region_code, word, translation, example_sentence) VALUES
('tagalog',    'Kumusta', 'Hello; how are you', 'Kumusta ka na?'),
('tagalog',    'Salamat', 'Thank you',          'Salamat sa tulong mo.'),
('tagalog',    'Tubig',   'Water',              'Gusto ko ng tubig.'),
('tagalog',    'Maganda', 'Beautiful',          'Maganda ang umaga.'),
('tagalog',    'Kain',    'Eat',                'Kain na tayo.'),

('cebuano',    'Kumusta', 'Hello; how are you', 'Kumusta ka?'),
('cebuano',    'Salamat', 'Thank you',          'Salamat kaayo.'),
('cebuano',    'Tubig',   'Water',              'Gusto ko og tubig.'),
('cebuano',    'Nindot',  'Beautiful',          'Nindot kaayo ang balay.'),
('cebuano',    'Kaon',    'Eat',                'Kaon na ta.'),

('hiligaynon', 'Kumusta', 'Hello; how are you', 'Kumusta ka?'),
('hiligaynon', 'Salamat', 'Thank you',          'Salamat gid.'),
('hiligaynon', 'Tubig',   'Water',              'Gusto ko sang tubig.'),
('hiligaynon', 'Matahom', 'Beautiful',          'Matahom ang bulak.'),
('hiligaynon', 'Kaon',    'Eat',                'Kaon na kita.');
