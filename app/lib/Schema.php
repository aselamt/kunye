<?php
declare(strict_types=1);

/** Veritabanı şeması ve sürüm yükseltmeleri (PRAGMA user_version) */
final class Schema
{
    public const VERSION = 1;

    public static function create(): void
    {
        Db::pdo()->exec(self::SQL);
        Db::pdo()->exec('PRAGMA user_version = ' . self::VERSION);
    }

    /** Sonraki sürümlerde eklenecek değişiklikler buraya yazılır */
    public static function migrate(): void
    {
        $v = (int) Db::val('PRAGMA user_version');
        if ($v >= self::VERSION) {
            return;
        }
        // örnek: if ($v < 2) { Db::pdo()->exec('ALTER TABLE …'); }
        Db::pdo()->exec('PRAGMA user_version = ' . self::VERSION);
    }

    private const SQL = <<<'SQL'
CREATE TABLE IF NOT EXISTS settings (
  k TEXT PRIMARY KEY,
  v TEXT NOT NULL
) WITHOUT ROWID;

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY,
  username TEXT NOT NULL UNIQUE COLLATE NOCASE,
  email TEXT UNIQUE COLLATE NOCASE,
  pass TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'user' CHECK (role IN ('admin','editor','user')),
  blocked INTEGER NOT NULL DEFAULT 0,
  created_at INTEGER NOT NULL,
  last_login INTEGER
);

CREATE TABLE IF NOT EXISTS tokens (
  id INTEGER PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  kind TEXT NOT NULL,
  selector TEXT NOT NULL UNIQUE,
  hash TEXT NOT NULL,
  expires INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS tokens_user ON tokens(user_id);

CREATE TABLE IF NOT EXISTS throttle (
  k TEXT NOT NULL,
  at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS throttle_k ON throttle(k, at);

CREATE TABLE IF NOT EXISTS sections (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL,
  sort INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS categories (
  id INTEGER PRIMARY KEY,
  section_id INTEGER REFERENCES sections(id) ON DELETE SET NULL,
  name TEXT NOT NULL,
  singular TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  icon TEXT NOT NULL DEFAULT 'tag',
  sort INTEGER NOT NULL DEFAULT 0,
  search TEXT NOT NULL DEFAULT '',
  item_count INTEGER NOT NULL DEFAULT 0,
  version INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS items (
  id INTEGER PRIMARY KEY,
  category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE RESTRICT,
  name TEXT NOT NULL,
  slug TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('published','draft')),
  search TEXT NOT NULL DEFAULT '',
  cover_id INTEGER,
  fav_count INTEGER NOT NULL DEFAULT 0,
  created_by INTEGER,
  updated_by INTEGER,
  created_at INTEGER NOT NULL,
  updated_at INTEGER NOT NULL,
  UNIQUE (category_id, slug)
);
CREATE INDEX IF NOT EXISTS items_cat ON items(category_id, status, created_at);
CREATE INDEX IF NOT EXISTS items_status ON items(status, fav_count);

-- şablon grubu (item_id NULL) ya da yalnızca bir öğeye özel grup
CREATE TABLE IF NOT EXISTS prop_groups (
  id INTEGER PRIMARY KEY,
  category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
  item_id INTEGER REFERENCES items(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  icon TEXT NOT NULL DEFAULT 'layers',
  sort INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS prop_groups_cat ON prop_groups(category_id, item_id);

-- type: number | single | multi | bool | text
CREATE TABLE IF NOT EXISTS props (
  id INTEGER PRIMARY KEY,
  category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
  group_id INTEGER NOT NULL REFERENCES prop_groups(id) ON DELETE CASCADE,
  item_id INTEGER REFERENCES items(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  short TEXT NOT NULL DEFAULT '',
  slug TEXT NOT NULL,
  type TEXT NOT NULL CHECK (type IN ('number','single','multi','bool','text')),
  unit TEXT NOT NULL DEFAULT '',
  icon TEXT NOT NULL DEFAULT '',
  sort INTEGER NOT NULL DEFAULT 0,
  filterable INTEGER NOT NULL DEFAULT 1,
  clickable INTEGER NOT NULL DEFAULT 1,
  in_list INTEGER NOT NULL DEFAULT 0,
  is_key INTEGER NOT NULL DEFAULT 0,
  in_eyebrow INTEGER NOT NULL DEFAULT 0,
  required INTEGER NOT NULL DEFAULT 0,
  archived INTEGER NOT NULL DEFAULT 0,
  UNIQUE (category_id, slug)
);
CREATE INDEX IF NOT EXISTS props_cat ON props(category_id, item_id);

CREATE TABLE IF NOT EXISTS options (
  id INTEGER PRIMARY KEY,
  prop_id INTEGER NOT NULL REFERENCES props(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  norm TEXT NOT NULL,
  slug TEXT NOT NULL,
  UNIQUE (prop_id, norm)
);
CREATE INDEX IF NOT EXISTS options_slug ON options(prop_id, slug);
CREATE INDEX IF NOT EXISTS options_norm ON options(norm);

-- değerler: tipe göre num / option_id / bool / text sütunlarından biri dolu
CREATE TABLE IF NOT EXISTS vals (
  item_id INTEGER NOT NULL REFERENCES items(id) ON DELETE CASCADE,
  prop_id INTEGER NOT NULL REFERENCES props(id) ON DELETE CASCADE,
  num REAL,
  option_id INTEGER REFERENCES options(id) ON DELETE CASCADE,
  bool INTEGER,
  text TEXT,
  sort INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS vals_item ON vals(item_id, prop_id);
CREATE INDEX IF NOT EXISTS vals_opt ON vals(option_id, item_id);
CREATE INDEX IF NOT EXISTS vals_num ON vals(prop_id, num);
CREATE INDEX IF NOT EXISTS vals_text ON vals(prop_id, text);

-- şablondaki bir özelliğin tek bir öğede gizlenmesi
CREATE TABLE IF NOT EXISTS hidden (
  item_id INTEGER NOT NULL REFERENCES items(id) ON DELETE CASCADE,
  prop_id INTEGER NOT NULL REFERENCES props(id) ON DELETE CASCADE,
  PRIMARY KEY (item_id, prop_id)
) WITHOUT ROWID;

CREATE TABLE IF NOT EXISTS images (
  id INTEGER PRIMARY KEY,
  item_id INTEGER REFERENCES items(id) ON DELETE CASCADE,
  file TEXT NOT NULL,
  w INTEGER NOT NULL,
  h INTEGER NOT NULL,
  widths TEXT NOT NULL,
  avif INTEGER NOT NULL DEFAULT 0,
  orig_bytes INTEGER NOT NULL DEFAULT 0,
  bytes INTEGER NOT NULL DEFAULT 0,
  sort INTEGER NOT NULL DEFAULT 0,
  created_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS images_item ON images(item_id, sort);
CREATE INDEX IF NOT EXISTS images_file ON images(file);

CREATE TABLE IF NOT EXISTS favorites (
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  item_id INTEGER NOT NULL REFERENCES items(id) ON DELETE CASCADE,
  created_at INTEGER NOT NULL,
  PRIMARY KEY (user_id, item_id)
) WITHOUT ROWID;
CREATE INDEX IF NOT EXISTS favorites_item ON favorites(item_id);

-- kind: fix (düzeltme) | fill (eksik bilgi) | new (yeni öğe)
CREATE TABLE IF NOT EXISTS suggestions (
  id INTEGER PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  item_id INTEGER REFERENCES items(id) ON DELETE CASCADE,
  category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
  kind TEXT NOT NULL,
  name TEXT NOT NULL DEFAULT '',
  status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','done','rejected')),
  note TEXT NOT NULL DEFAULT '',
  source TEXT NOT NULL DEFAULT '',
  reply TEXT NOT NULL DEFAULT '',
  created_at INTEGER NOT NULL,
  reviewed_by INTEGER,
  reviewed_at INTEGER
);
CREATE INDEX IF NOT EXISTS suggestions_status ON suggestions(status, created_at);
CREATE INDEX IF NOT EXISTS suggestions_user ON suggestions(user_id, status);

CREATE TABLE IF NOT EXISTS changes (
  id INTEGER PRIMARY KEY,
  suggestion_id INTEGER NOT NULL REFERENCES suggestions(id) ON DELETE CASCADE,
  prop_id INTEGER REFERENCES props(id) ON DELETE CASCADE,
  old TEXT,
  new TEXT,
  status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','approved','rejected')),
  final TEXT
);
CREATE INDEX IF NOT EXISTS changes_s ON changes(suggestion_id);

CREATE TABLE IF NOT EXISTS history (
  id INTEGER PRIMARY KEY,
  item_id INTEGER,
  user_id INTEGER,
  action TEXT NOT NULL,
  detail TEXT NOT NULL DEFAULT '',
  created_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS history_item ON history(item_id, id);
SQL;
}
