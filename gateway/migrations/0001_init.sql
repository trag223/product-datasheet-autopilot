CREATE TABLE IF NOT EXISTS license_installations (
  license_id TEXT PRIMARY KEY NOT NULL,
  install_id TEXT UNIQUE,
  site_hash TEXT,
  active INTEGER NOT NULL DEFAULT 0,
  paid_through INTEGER NOT NULL DEFAULT 0,
  recognized_arr_usd REAL NOT NULL DEFAULT 0,
  updated_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS request_nonces (
  license_id TEXT NOT NULL,
  nonce TEXT NOT NULL,
  expires_at INTEGER NOT NULL,
  PRIMARY KEY (license_id, nonce)
);

CREATE INDEX IF NOT EXISTS request_nonces_expiry ON request_nonces (expires_at);

CREATE TABLE IF NOT EXISTS usage_daily (
  license_id TEXT NOT NULL,
  day TEXT NOT NULL,
  calls INTEGER NOT NULL DEFAULT 0,
  input_tokens INTEGER NOT NULL DEFAULT 0,
  output_tokens INTEGER NOT NULL DEFAULT 0,
  cost_usd REAL NOT NULL DEFAULT 0,
  PRIMARY KEY (license_id, day)
);

CREATE TABLE IF NOT EXISTS webhook_events (
  event_id TEXT PRIMARY KEY NOT NULL,
  received_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS health_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  kind TEXT NOT NULL,
  severity TEXT NOT NULL,
  detail TEXT NOT NULL,
  created_at INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS health_events_kind_created ON health_events (kind, created_at DESC);
