-- DB
CREATE DATABASE IF NOT EXISTS airsoft_central CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE airsoft_central;

-- USERS
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  pass_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ARENAS (campo/dono)
CREATE TABLE IF NOT EXISTS arenas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  owner_user_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- MATCHES (duas equipas + códigos)
CREATE TABLE IF NOT EXISTS matches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  arena_id INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME DEFAULT NULL,
  team_a_name VARCHAR(80) NOT NULL,
  team_b_name VARCHAR(80) NOT NULL,
  team_a_code VARCHAR(10) NOT NULL UNIQUE,
  team_b_code VARCHAR(10) NOT NULL UNIQUE,
  code_display_mode ENUM('text','qr') NOT NULL DEFAULT 'text',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (arena_id) REFERENCES arenas(id) ON DELETE CASCADE,
  INDEX idx_arena (arena_id)
) ENGINE=InnoDB;

-- Membros do match e lado (A/B)
CREATE TABLE IF NOT EXISTS match_members (
  match_id INT NOT NULL,
  user_id INT NOT NULL,
  side ENUM('A','B') NOT NULL,
  joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (match_id, user_id),
  FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- MAPAS por piso (URL pública)
CREATE TABLE IF NOT EXISTS maps (
  id INT AUTO_INCREMENT PRIMARY KEY,
  arena_id INT NOT NULL,
  floor INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  map_url VARCHAR(500) NOT NULL,
  UNIQUE KEY uniq_arena_floor (arena_id, floor),
  FOREIGN KEY (arena_id) REFERENCES arenas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- BEACONS registados por arena/piso (opcional; app pode vir hardcoded também)
CREATE TABLE IF NOT EXISTS beacons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  arena_id INT NOT NULL,
  uuid CHAR(36) NOT NULL,
  major INT NOT NULL,
  minor INT NOT NULL,
  floor INT NOT NULL,
  tx_power INT DEFAULT -59,
  label VARCHAR(120) DEFAULT NULL,
  UNIQUE KEY uniq_beacon (arena_id, uuid, major, minor),
  FOREIGN KEY (arena_id) REFERENCES arenas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- SCANS recebidos (histórico bruto)
CREATE TABLE IF NOT EXISTS scans (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  match_id INT NOT NULL,
  team_id INT NOT NULL,
  player_id INT NOT NULL,
  floor INT NOT NULL,
  payload JSON NOT NULL,
  INDEX idx_match (match_id),
  INDEX idx_player (player_id),
  FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Estado atual do player (piso)
CREATE TABLE IF NOT EXISTS player_state (
  player_id INT PRIMARY KEY,
  last_floor INT NULL,
  last_change_at TIMESTAMP NULL,
  avg_rssi FLOAT NULL
) ENGINE=InnoDB;

-- Tabela lógica players (um player “app” por user+team)
CREATE TABLE IF NOT EXISTS players (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  team_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_team (user_id, team_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
