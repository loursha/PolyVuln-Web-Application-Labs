-- PolyVuln Bank - core schema
CREATE DATABASE IF NOT EXISTS polybank CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE polybank;

CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password      CHAR(32)     NOT NULL,
  full_name     VARCHAR(120) NOT NULL,
  email         VARCHAR(160) NOT NULL,
  phone         VARCHAR(30)  DEFAULT NULL,
  dob           DATE         DEFAULT NULL,
  address       VARCHAR(255) DEFAULT NULL,
  role          VARCHAR(20)  NOT NULL DEFAULT 'customer',
  avatar        VARCHAR(255) DEFAULT 'assets/img/avatars/default.png',
  status        VARCHAR(20)  NOT NULL DEFAULT 'active',
  last_login    DATETIME     DEFAULT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE accounts (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT NOT NULL,
  account_number VARCHAR(16) NOT NULL UNIQUE,
  type           VARCHAR(20) NOT NULL DEFAULT 'Checking',
  nickname       VARCHAR(60) DEFAULT NULL,
  balance        DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  currency       CHAR(3) NOT NULL DEFAULT 'USD',
  status         VARCHAR(20) NOT NULL DEFAULT 'active',
  opened_at      DATE NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE transactions (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  reference           VARCHAR(24) NOT NULL,
  account_id          INT NOT NULL,
  direction           ENUM('credit','debit') NOT NULL,
  amount              DECIMAL(14,2) NOT NULL,
  balance_after       DECIMAL(14,2) DEFAULT NULL,
  counterparty_name   VARCHAR(120) DEFAULT NULL,
  counterparty_account VARCHAR(32) DEFAULT NULL,
  description         VARCHAR(160) NOT NULL,
  remarks             TEXT,
  category            VARCHAR(40) DEFAULT 'Other',
  channel             VARCHAR(30) DEFAULT 'Online',
  status              VARCHAR(20) DEFAULT 'Completed',
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (account_id), INDEX (created_at),
  FOREIGN KEY (account_id) REFERENCES accounts(id)
) ENGINE=InnoDB;

CREATE TABLE beneficiaries (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT NOT NULL,
  nickname       VARCHAR(80) NOT NULL,
  holder_name    VARCHAR(120) NOT NULL,
  account_number VARCHAR(32) NOT NULL,
  bank_name      VARCHAR(80) NOT NULL DEFAULT 'PolyVuln Bank',
  routing        VARCHAR(20) DEFAULT 'PVB0001021',
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE cards (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  account_id  INT NOT NULL,
  kind        VARCHAR(20) NOT NULL DEFAULT 'Debit',
  network     VARCHAR(20) NOT NULL DEFAULT 'Visa',
  last4       CHAR(4) NOT NULL,
  holder      VARCHAR(120) NOT NULL,
  expiry      CHAR(5) NOT NULL,
  daily_limit DECIMAL(10,2) NOT NULL DEFAULT 2500.00,
  status      VARCHAR(20) NOT NULL DEFAULT 'active',
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE tickets (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  subject     VARCHAR(160) NOT NULL,
  category    VARCHAR(40) NOT NULL,
  message     TEXT NOT NULL,
  status      VARCHAR(20) NOT NULL DEFAULT 'Open',
  reply       TEXT,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE password_resets (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  code        CHAR(4) NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE topup_intents (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  intent_ref  VARCHAR(32) NOT NULL UNIQUE,
  user_id     INT NOT NULL,
  account_id  INT NOT NULL,
  amount      DECIMAL(14,2) NOT NULL,
  status      VARCHAR(20) NOT NULL DEFAULT 'pending',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE notices (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  title      VARCHAR(160) NOT NULL,
  body       TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
