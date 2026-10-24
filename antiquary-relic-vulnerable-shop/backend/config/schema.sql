-- ============================================================================
-- schema.sql -- The Antiquary & Relic Co. database schema
-- Run: mysql -u root antiques_shop < schema.sql
-- ============================================================================

DROP TABLE IF EXISTS wishlist_items;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS provenance_notes;
DROP TABLE IF EXISTS artifacts;
DROP TABLE IF EXISTS eras;
DROP TABLE IF EXISTS authenticators;
DROP TABLE IF EXISTS appraisal_requests;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) UNIQUE NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    avatar VARCHAR(255) DEFAULT 'default-avatar.jpg',
    reset_token VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE eras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT
) ENGINE=InnoDB;

CREATE TABLE artifacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL,
    era_id INT,
    price DECIMAL(10,2) NOT NULL,
    origin VARCHAR(150),
    condition_code VARCHAR(50),
    description TEXT,
    image VARCHAR(255),
    stock INT DEFAULT 5,
    featured TINYINT(1) DEFAULT 0,
    FOREIGN KEY (era_id) REFERENCES eras(id)
) ENGINE=InnoDB;

CREATE TABLE provenance_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    artifact_id INT NOT NULL,
    username VARCHAR(64) NOT NULL,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (artifact_id) REFERENCES artifacts(id)
) ENGINE=InnoDB;

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    status VARCHAR(50) DEFAULT 'processing',
    shipping_address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    artifact_id INT NOT NULL,
    quantity INT NOT NULL,
    price_each DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (artifact_id) REFERENCES artifacts(id)
) ENGINE=InnoDB;

CREATE TABLE wishlist_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    artifact_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (artifact_id) REFERENCES artifacts(id)
) ENGINE=InnoDB;

CREATE TABLE authenticators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    hostname VARCHAR(255) NOT NULL,
    contact_email VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE appraisal_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    item_description TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
