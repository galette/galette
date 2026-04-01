-- Galette API tables
-- Copyright © 2003-2026 The Galette Team
-- License: GPL-3.0

-- API clients (OAuth2 client credentials)
CREATE TABLE IF NOT EXISTS {PREFIX}api_client (
    client_id VARCHAR(80) NOT NULL,
    client_secret_hash VARCHAR(255) NOT NULL,
    client_name VARCHAR(128) NOT NULL,
    redirect_uri TEXT,
    is_trusted BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (client_id)
);

-- API refresh tokens
CREATE TABLE IF NOT EXISTS {PREFIX}api_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_adh INT(10) UNSIGNED,
    client_id VARCHAR(80),
    token_hash VARCHAR(64) NOT NULL,
    allowed_scope TEXT,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    is_revoked BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (id),
    UNIQUE KEY uk_api_tokens_hash (token_hash)
);
