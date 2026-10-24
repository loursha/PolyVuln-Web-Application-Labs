-- Fête de Cadre — photo frame boutique (intentionally vulnerable training lab)
CREATE DATABASE IF NOT EXISTS frameshop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE frameshop;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(40) NOT NULL UNIQUE,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash CHAR(32) NOT NULL,              -- unsalted MD5 (CWE-328)
  full_name VARCHAR(100) NOT NULL,
  phone VARCHAR(30) DEFAULT '',
  address VARCHAR(255) DEFAULT '',
  role ENUM('customer','staff','admin') NOT NULL DEFAULT 'customer',
  is_trade TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  preferences TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  category VARCHAR(40) NOT NULL,
  description TEXT NOT NULL,
  material VARCHAR(120) NOT NULL DEFAULT '',
  sizes VARCHAR(120) NOT NULL DEFAULT '8x10in',
  price DECIMAL(10,2) NOT NULL,
  image VARCHAR(160) NOT NULL,
  stock INT NOT NULL DEFAULT 25,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  trade_only TINYINT(1) NOT NULL DEFAULT 0
);

CREATE TABLE reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  user_id INT NOT NULL,
  rating TINYINT NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(20) NOT NULL UNIQUE,
  user_id INT NOT NULL,
  ship_name VARCHAR(100) NOT NULL,
  ship_address VARCHAR(255) NOT NULL,
  shipping_method ENUM('standard','express') NOT NULL DEFAULT 'standard',
  gift_note VARCHAR(300) DEFAULT '',
  status ENUM('placed','framing','shipped','delivered','cancelled') NOT NULL DEFAULT 'placed',
  subtotal DECIMAL(10,2) NOT NULL,
  discount DECIMAL(10,2) NOT NULL DEFAULT 0,
  shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL,
  card_last4 CHAR(4) DEFAULT '0000',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  product_name VARCHAR(120) NOT NULL,
  category VARCHAR(40) NOT NULL,
  size VARCHAR(20) NOT NULL DEFAULT '8x10in',
  unit_price DECIMAL(10,2) NOT NULL,
  qty INT NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

CREATE TABLE coupons (
  code VARCHAR(32) PRIMARY KEY,
  discount_pct INT NOT NULL,
  expires_at DATETIME NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  code CHAR(4) NOT NULL,                         -- 4-digit code (CWE-640)
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE custom_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  frame_style VARCHAR(60) NOT NULL,
  dimensions VARCHAR(60) NOT NULL,
  budget INT NOT NULL,
  notes VARCHAR(600) DEFAULT '',
  reference_url VARCHAR(500) DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE sessions (
  sid VARCHAR(64) NOT NULL PRIMARY KEY,
  data MEDIUMTEXT NOT NULL,
  expires_at DATETIME NOT NULL,
  INDEX (expires_at)
);

INSERT INTO users (username,email,password_hash,full_name,phone,address,role,is_trade,preferences) VALUES
('admin','admin@fetedecadre.local',MD5('gallery1'),'Colette Prideaux','+1 555 0300','7 Atelier Mews, Providence, RI','admin',1,'{"newsletter":true,"sms":false,"favoriteStyle":"Gilt","roomNotes":""}'),
('framer','framer@fetedecadre.local',MD5('Studio2024!'),'Theo Marsh','+1 555 0301','7 Atelier Mews, Providence, RI','staff',0,'{"newsletter":false,"sms":false,"favoriteStyle":"","roomNotes":""}'),
('bianca','bianca@example.com',MD5('bianca123'),'Bianca Lowe','+1 555 0342','19 Benefit St, Providence, RI','customer',1,'{"newsletter":true,"sms":true,"favoriteStyle":"Oak","roomNotes":"North-facing wall, soft light"}'),
('devon','devon@example.com',MD5('password1'),'Devon Clarke','+1 555 0377','66 Hope St, Pawtucket, RI','customer',0,'{"newsletter":true,"sms":false,"favoriteStyle":"Black","roomNotes":""}'),
('priya','priya@example.com',MD5('sunshine'),'Priya Nair','+1 555 0390','4 Wickenden St, Providence, RI','customer',0,'{"newsletter":false,"sms":false,"favoriteStyle":"","roomNotes":""}');

INSERT INTO products (name,slug,category,description,material,sizes,price,image,stock,featured,trade_only) VALUES
('Florentine Gilt Frame','florentine-gilt-frame','Ornate','A hand-finished gold-leaf frame with a carved acanthus border, the kind used in grand portrait galleries.','Gold leaf on ayous wood','5x7in,8x10in,11x14in,16x20in',148.00,'/assets/img/products/ornate-gold.jpg',20,1,0),
('Studio Black Gallery Frame','studio-black-gallery-frame','Gallery','A clean matte-black profile with a wide ivory mat. The default choice of galleries for a reason.','Matte-lacquered ash','5x7in,8x10in,11x14in,16x20in,24x36in',54.00,'/assets/img/products/black-gallery.jpg',60,1,0),
('Natural Oak Frame','natural-oak-frame','Wood','A warm, lightly waxed oak frame with a visible grain. Pairs with everything from prints to pressed flowers.','Solid European oak','5x7in,8x10in,11x14in,16x20in',62.00,'/assets/img/products/oak-natural.jpg',45,1,0),
('Nordic White Frame','nordic-white-frame','Wood','A soft chalk-white frame with a slim profile, made for bright rooms and pale prints.','Painted tulipwood','5x7in,8x10in,11x14in',44.00,'/assets/img/products/white-wood.jpg',50,0,0),
('Walnut Heritage Frame','walnut-heritage-frame','Wood','A deep walnut frame with a satin finish and a chamfered inner edge. Ages beautifully.','Solid American walnut','8x10in,11x14in,16x20in',78.00,'/assets/img/products/walnut-classic.jpg',28,1,0),
('Brushed Silver Frame','brushed-silver-frame','Metal','A fine brushed-aluminium frame with square corners. Light, modern and fingerprint-resistant.','Anodised aluminium','5x7in,8x10in,11x14in,16x20in',58.00,'/assets/img/products/silver-metal.jpg',40,0,0),
('Belle Epoque Ornate Frame','belle-epoque-ornate-frame','Ornate','An antique-style frame with an elaborate scrolled border and a warm patina. A statement piece.','Resin-cast, hand-antiqued','8x10in,11x14in,16x20in',132.00,'/assets/img/products/vintage-ornate.jpg',16,0,0),
('Floating Glass Frame','floating-glass-frame','Gallery','Two panes of glass that hold a photo as if it floats, with a slim wood surround. Shows both sides.','Tempered glass, maple','5x7in,8x10in,11x14in',68.00,'/assets/img/products/floating-frame.jpg',34,1,0),
('Diploma & Certificate Frame','diploma-certificate-frame','Document','A dark hardwood frame with a double mat sized for A4 and US Letter certificates.','Stained beech','A4,US Letter,11x14in',72.00,'/assets/img/products/certificate.jpg',38,0,0),
('Verona Tabletop Frame','verona-tabletop-frame','Tabletop','A champagne-toned desk frame with a velvet-backed easel stand. Stands portrait or landscape.','Brass-tone zinc alloy','4x6in,5x7in',39.00,'/assets/img/products/tabletop.jpg',55,0,0),
('Nine-Opening Collage Frame','nine-opening-collage-frame','Collage','A large wall frame with nine cut-mat openings for a grid of memories. Includes a hanging template.','Ash with cotton-rag mat','Holds 9x 4x6in',96.00,'/assets/img/products/collage-multi.jpg',22,1,0),
('Conservation Archival Frame','conservation-archival-frame','Document','A UV-filtering, acid-free museum frame for artwork you want to keep for decades. Trade clients only.','Museum glass, archival mat','11x14in,16x20in,24x36in',210.00,'/assets/img/products/floating-frame.jpg',8,0,1);

INSERT INTO reviews (product_id,user_id,rating,body,created_at) VALUES
(1,3,5,'The gilding is genuinely beautiful in daylight. Framed my grandmother''s portrait and it looks like a museum piece.',DATE_SUB(NOW(),INTERVAL 19 DAY)),
(2,4,5,'Bought six for a stair gallery. Consistent, well packed, and the mats are properly cut.',DATE_SUB(NOW(),INTERVAL 12 DAY)),
(3,5,4,'Lovely oak, though one corner join was slightly proud. Still very happy.',DATE_SUB(NOW(),INTERVAL 8 DAY)),
(8,3,5,'The floating glass makes a pressed-fern print look like it is suspended in air.',DATE_SUB(NOW(),INTERVAL 5 DAY)),
(5,4,5,'Walnut is rich and the finish is flawless. Worth the extra over the oak.',DATE_SUB(NOW(),INTERVAL 3 DAY));

INSERT INTO coupons (code,discount_pct,expires_at,active) VALUES
('FRAME15',15,'2027-12-31 23:59:59',1),
('WELCOME10',10,'2027-12-31 23:59:59',1),
('GALLERY20',20,'2025-09-30 23:59:59',1);

INSERT INTO orders (order_number,user_id,ship_name,ship_address,shipping_method,gift_note,status,subtotal,discount,shipping_fee,total,card_last4,created_at) VALUES
('FC-30411',3,'Bianca Lowe','19 Benefit St, Providence, RI','standard','','delivered',148.00,22.20,0,125.80,'4242',DATE_SUB(NOW(),INTERVAL 14 DAY)),
('FC-30416',4,'Devon Clarke','66 Hope St, Pawtucket, RI','standard','','delivered',106.00,0,7.50,113.50,'1881',DATE_SUB(NOW(),INTERVAL 12 DAY)),
('FC-30420',5,'Priya Nair','4 Wickenden St, Providence, RI','express','For the new flat','delivered',62.00,0,14.00,76.00,'5100',DATE_SUB(NOW(),INTERVAL 10 DAY)),
('FC-30424',4,'Devon Clarke','66 Hope St, Pawtucket, RI','standard','','delivered',78.00,0,7.50,85.50,'1881',DATE_SUB(NOW(),INTERVAL 8 DAY)),
('FC-30429',3,'Bianca Lowe','19 Benefit St, Providence, RI','express','','delivered',96.00,14.40,0,81.60,'4242',DATE_SUB(NOW(),INTERVAL 6 DAY)),
('FC-30433',5,'Arun Nair','4 Wickenden St, Providence, RI','standard','Happy retirement','delivered',72.00,0,7.50,79.50,'5100',DATE_SUB(NOW(),INTERVAL 5 DAY)),
('FC-30437',4,'Devon Clarke','66 Hope St, Pawtucket, RI','standard','','delivered',132.00,0,0,132.00,'1881',DATE_SUB(NOW(),INTERVAL 3 DAY)),
('FC-30440',5,'Priya Nair','4 Wickenden St, Providence, RI','standard','','shipped',54.00,0,7.50,61.50,'5100',DATE_SUB(NOW(),INTERVAL 2 DAY)),
('FC-30444',3,'Bianca Lowe','19 Benefit St, Providence, RI','express','','framing',126.00,18.90,0,107.10,'4242',DATE_SUB(NOW(),INTERVAL 1 DAY)),
('FC-30447',4,'Devon Clarke','66 Hope St, Pawtucket, RI','standard','','placed',44.00,0,7.50,51.50,'1881',NOW());

INSERT INTO order_items (order_id,product_id,product_name,category,size,unit_price,qty) VALUES
(1,1,'Florentine Gilt Frame','Ornate','8x10in',148.00,1),
(2,3,'Natural Oak Frame','Wood','11x14in',62.00,1),(2,4,'Nordic White Frame','Wood','8x10in',44.00,1),
(3,3,'Natural Oak Frame','Wood','8x10in',62.00,1),
(4,5,'Walnut Heritage Frame','Wood','11x14in',78.00,1),
(5,11,'Nine-Opening Collage Frame','Collage','Holds 9x 4x6in',96.00,1),
(6,9,'Diploma & Certificate Frame','Document','US Letter',72.00,1),
(7,7,'Belle Epoque Ornate Frame','Ornate','11x14in',132.00,1),
(8,2,'Studio Black Gallery Frame','Gallery','11x14in',54.00,1),
(9,11,'Nine-Opening Collage Frame','Collage','Holds 9x 4x6in',96.00,1),(9,10,'Verona Tabletop Frame','Tabletop','5x7in',39.00,1),
(10,4,'Nordic White Frame','Wood','8x10in',44.00,1);

CREATE USER IF NOT EXISTS 'frame_app'@'%' IDENTIFIED BY 'Frame_App_2024!';
GRANT SELECT, INSERT, UPDATE, DELETE ON frameshop.* TO 'frame_app'@'%';
FLUSH PRIVILEGES;
