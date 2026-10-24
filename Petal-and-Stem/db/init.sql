-- Petal & Stem - schema + seed data (intentionally vulnerable training lab)
CREATE DATABASE IF NOT EXISTS petalstem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE petalstem;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(40) NOT NULL UNIQUE,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash CHAR(32) NOT NULL,              -- unsalted MD5 (CWE-328)
  full_name VARCHAR(100) NOT NULL,
  phone VARCHAR(30) DEFAULT '',
  address VARCHAR(255) DEFAULT '',
  role ENUM('customer','staff','admin') NOT NULL DEFAULT 'customer',
  is_vip TINYINT(1) NOT NULL DEFAULT 0,
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
  price DECIMAL(10,2) NOT NULL,
  image VARCHAR(120) NOT NULL,
  stock INT NOT NULL DEFAULT 20,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  vip_only TINYINT(1) NOT NULL DEFAULT 0
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
  recipient_name VARCHAR(100) NOT NULL,
  delivery_address VARCHAR(255) NOT NULL,
  delivery_date DATE NOT NULL,
  message_card VARCHAR(300) DEFAULT '',
  status ENUM('received','arranging','out_for_delivery','delivered','cancelled') NOT NULL DEFAULT 'received',
  subtotal DECIMAL(10,2) NOT NULL,
  discount DECIMAL(10,2) NOT NULL DEFAULT 0,
  delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
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
  code CHAR(4) NOT NULL,                        -- 4 digit code (CWE-640)
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE custom_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  occasion VARCHAR(60) NOT NULL,
  palette VARCHAR(60) NOT NULL,
  budget INT NOT NULL,
  notes VARCHAR(600) DEFAULT '',
  reference_url VARCHAR(500) DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- express-mysql-session store (pre-created so the app user needs no CREATE grant)
CREATE TABLE sessions (
  session_id VARCHAR(128) COLLATE utf8mb4_bin NOT NULL PRIMARY KEY,
  expires INT UNSIGNED NOT NULL,
  data MEDIUMTEXT COLLATE utf8mb4_bin
);

-- ---------- Seed users (MD5 of the plaintext password) ----------
INSERT INTO users (username,email,password_hash,full_name,phone,address,role,is_vip) VALUES
('admin','admin@petalstem.local',MD5('sunflower1'),'Ivy Hart','+1 555 0100','12 Greenhouse Row, Portland, OR','admin',1),
('florist','florist@petalstem.local',MD5('Bloom2024!'),'Fern Okafor','+1 555 0101','12 Greenhouse Row, Portland, OR','staff',0),
('daisy','daisy@example.com',MD5('daisy123'),'Daisy Moreno','+1 555 0142','88 Larch St, Portland, OR','customer',1),
('jasper','jasper@example.com',MD5('password1'),'Jasper Lin','+1 555 0177','4 Willow Ct, Beaverton, OR','customer',0),
('rosalind','rosalind@example.com',MD5('rosebud'),'Rosalind Achebe','+1 555 0190','301 Alder Ave, Portland, OR','customer',0);

-- ---------- Products ----------
INSERT INTO products (name,slug,category,description,price,image,stock,featured,vip_only) VALUES
('Crimson Rose Dozen','crimson-rose-dozen','Bouquets','Twelve long-stem Freedom roses, hand-tied with eucalyptus and wrapped in kraft paper. Our most requested anniversary bouquet.',64.00,'/images/products/red-roses.jpg',30,1,0),
('Orange Tulip Bundle','orange-tulip-bundle','Bouquets','Fifteen Dutch tulips in sunset orange. Tulips keep growing in the vase, so give them a fresh trim every other day.',38.00,'/images/products/tulips.jpg',40,1,0),
('Meadow Sunflower Posy','meadow-sunflower-posy','Bouquets','Sunflowers, wild chamomile and blue cornflower gathered the way they grow in late-summer fields.',45.00,'/images/products/sunflowers.jpg',25,1,0),
('White Phalaenopsis Orchid','white-phalaenopsis-orchid','Plants','A twin-spike moth orchid in a brushed metal planter. Blooms for 8 to 12 weeks with weekly watering.',52.00,'/images/products/orchid.jpg',15,1,0),
('Blush Peony Armful','blush-peony-armful','Seasonal','Sarah Bernhardt and Coral Charm peonies at their fullest. Available while the season lasts.',72.00,'/images/products/peonies.jpg',12,1,0),
('Regal Lily Stems','regal-lily-stems','Bouquets','Five stems of fragrant regal lilies. We remove the pollen anthers so they won''t stain tablecloths.',48.00,'/images/products/lilies.jpg',22,0,0),
('Dried Lavender Bunch','dried-lavender-bunch','Seasonal','Provence lavender, air-dried and tied with linen. Keeps its scent for a full year.',22.00,'/images/products/lavender.jpg',60,0,0),
('Blue Hydrangea Cloud','blue-hydrangea-cloud','Seasonal','Three mophead hydrangea heads in deep coastal blue. Mist the petals daily to keep them full.',58.00,'/images/products/hydrangea.jpg',18,0,0),
('Daisy & Bramble Jar','daisy-bramble-jar','Bouquets','Ox-eye daisies and blackberry stems in a reusable glass jar. A cheerful desk-sized arrangement.',34.00,'/images/products/daisies.jpg',35,0,0),
('Ivory Carnation Bunch','ivory-carnation-bunch','Bouquets','Twenty ruffled ivory carnations, the longest-lasting cut flower we sell.',26.00,'/images/products/carnations.jpg',50,0,0),
('Terracotta Succulent','terracotta-succulent','Plants','A sculptural aloe in a hand-thrown terracotta pot. Water once every two weeks.',24.00,'/images/products/succulent.jpg',40,0,0),
('Bridal Blush Bouquet','bridal-blush-bouquet','Weddings','Garden roses, spray roses and lisianthus in soft blush, finished with a silk ribbon wrap. Order 3 weeks ahead.',145.00,'/images/products/wedding.jpg',6,1,0),
('Lime Ranunculus Duo','lime-ranunculus-duo','Seasonal','Two oversized green ranunculus blooms with layered, paper-thin petals.',30.00,'/images/products/ranunculus.jpg',28,0,0),
('Petal Club Market Armful','petal-club-market-armful','Reserve','The florist''s pick of the morning flower market: chrysanthemum, gerbera and stock in every colour. Petal Club members only.',89.00,'/images/products/reserve.jpg',8,0,1);

-- ---------- Reviews ----------
INSERT INTO reviews (product_id,user_id,rating,body,created_at) VALUES
(1,3,5,'Delivered the same afternoon and still going strong after nine days. The eucalyptus smells wonderful.',DATE_SUB(NOW(),INTERVAL 20 DAY)),
(1,4,4,'Lovely roses, one stem had a bent neck but the rest were perfect.',DATE_SUB(NOW(),INTERVAL 12 DAY)),
(2,5,5,'The tulips doubled in height! My mum loved them.',DATE_SUB(NOW(),INTERVAL 8 DAY)),
(4,3,5,'Second orchid from Petal & Stem, both reflowered the next year.',DATE_SUB(NOW(),INTERVAL 30 DAY)),
(5,5,5,'Peonies opened fully within two days. Worth every penny.',DATE_SUB(NOW(),INTERVAL 4 DAY));

-- ---------- Coupons ----------
INSERT INTO coupons (code,discount_pct,expires_at,active) VALUES
('SPRING15',15,'2027-12-31 23:59:59',1),
('WELCOME10',10,'2027-12-31 23:59:59',1),
('MOTHERSDAY20',20,'2025-05-31 23:59:59',1);

-- ---------- Orders ----------
INSERT INTO orders (order_number,user_id,recipient_name,delivery_address,delivery_date,message_card,status,subtotal,discount,delivery_fee,total,card_last4,created_at) VALUES
('PS-10421',3,'Carmen Moreno','17 Birch Ln, Portland, OR',DATE(DATE_SUB(NOW(),INTERVAL 13 DAY)),'Happy birthday Mamá!','delivered',64.00,0,0,64.00,'4242',DATE_SUB(NOW(),INTERVAL 14 DAY)),
('PS-10426',4,'Mei Lin','9 Cedar Rd, Beaverton, OR',DATE(DATE_SUB(NOW(),INTERVAL 12 DAY)),'Congrats on the new job','delivered',38.00,0,6.95,44.95,'1881',DATE_SUB(NOW(),INTERVAL 12 DAY)),
('PS-10430',5,'Theo Achebe','301 Alder Ave, Portland, OR',DATE(DATE_SUB(NOW(),INTERVAL 10 DAY)),'','delivered',145.00,0,0,145.00,'5100',DATE_SUB(NOW(),INTERVAL 11 DAY)),
('PS-10433',3,'Daisy Moreno','88 Larch St, Portland, OR',DATE(DATE_SUB(NOW(),INTERVAL 9 DAY)),'Treat yourself','delivered',96.00,14.40,0,81.60,'4242',DATE_SUB(NOW(),INTERVAL 9 DAY)),
('PS-10438',4,'Ana Ruiz','55 Pine St, Portland, OR',DATE(DATE_SUB(NOW(),INTERVAL 7 DAY)),'Get well soon','delivered',52.00,0,6.95,58.95,'1881',DATE_SUB(NOW(),INTERVAL 7 DAY)),
('PS-10442',5,'Grace Kim','2 Spruce Way, Gresham, OR',DATE(DATE_SUB(NOW(),INTERVAL 5 DAY)),'Thinking of you','delivered',72.00,0,0,72.00,'5100',DATE_SUB(NOW(),INTERVAL 5 DAY)),
('PS-10447',3,'Lucía Moreno','17 Birch Ln, Portland, OR',DATE(DATE_SUB(NOW(),INTERVAL 3 DAY)),'','delivered',140.00,0,0,140.00,'4242',DATE_SUB(NOW(),INTERVAL 4 DAY)),
('PS-10451',4,'Jasper Lin','4 Willow Ct, Beaverton, OR',DATE(DATE_SUB(NOW(),INTERVAL 2 DAY)),'','delivered',48.00,0,6.95,54.95,'1881',DATE_SUB(NOW(),INTERVAL 2 DAY)),
('PS-10455',5,'Omar Haddad','40 Elm St, Portland, OR',DATE(NOW()),'Happy anniversary','out_for_delivery',117.00,0,0,117.00,'5100',DATE_SUB(NOW(),INTERVAL 1 DAY)),
('PS-10457',4,'Priya Shah','18 Maple Dr, Hillsboro, OR',DATE(DATE_ADD(NOW(),INTERVAL 1 DAY)),'Welcome to the team!','arranging',86.00,0,0,86.00,'1881',NOW()),
('PS-10458',3,'Daisy Moreno','88 Larch St, Portland, OR',DATE(DATE_ADD(NOW(),INTERVAL 2 DAY)),'','received',58.00,8.70,0,49.30,'4242',NOW());

INSERT INTO order_items (order_id,product_id,product_name,category,unit_price,qty) VALUES
(1,1,'Crimson Rose Dozen','Bouquets',64.00,1),(2,2,'Orange Tulip Bundle','Bouquets',38.00,1),
(3,12,'Bridal Blush Bouquet','Weddings',145.00,1),(4,6,'Regal Lily Stems','Bouquets',48.00,2),
(5,4,'White Phalaenopsis Orchid','Plants',52.00,1),(6,5,'Blush Peony Armful','Seasonal',72.00,1),
(7,13,'Lime Ranunculus Duo','Seasonal',30.00,1),(7,7,'Dried Lavender Bunch','Seasonal',22.00,1),(7,1,'Crimson Rose Dozen','Bouquets',64.00,1),
(7,11,'Terracotta Succulent','Plants',24.00,1),
(8,6,'Regal Lily Stems','Bouquets',48.00,1),(9,5,'Blush Peony Armful','Seasonal',72.00,1),(9,3,'Meadow Sunflower Posy','Bouquets',45.00,1),
(10,4,'White Phalaenopsis Orchid','Plants',52.00,1),(10,9,'Daisy & Bramble Jar','Bouquets',34.00,1),(11,8,'Blue Hydrangea Cloud','Seasonal',58.00,1);

-- ---------- Least-privilege application account ----------
-- No FILE, CREATE, DROP or GRANT privileges: limits the blast radius of the SQLi lab.
CREATE USER IF NOT EXISTS 'petal_app'@'%' IDENTIFIED BY 'Petal_App_2024!';
GRANT SELECT, INSERT, UPDATE, DELETE ON petalstem.* TO 'petal_app'@'%';
FLUSH PRIVILEGES;
