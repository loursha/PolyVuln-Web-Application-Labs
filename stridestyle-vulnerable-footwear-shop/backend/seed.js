/**
 * seed.js -- StrideStyle Footwear Co. database seed data
 * Run: node seed.js
 *
 * Drops and recreates all tables (via schema.sql) then inserts demo data.
 */
const fs = require("fs");
const path = require("path");
const crypto = require("crypto");
const mysql = require("mysql2/promise");

const DB_CONFIG = {
  host: process.env.DB_HOST || "localhost",
  user: process.env.DB_USER || "footwear_app",
  password: process.env.DB_PASSWORD || "FootwearApp2024!",
  database: process.env.DB_NAME || "footwear_shop",
  multipleStatements: true,
};

// VULNERABLE (CWE-328): unsalted MD5 password hashing -- see full note in
// server.js next to the login/register routes. Duplicated here so seeded
// users match the same (weak) hashing the app uses at runtime.
function md5(input) {
  return crypto.createHash("md5").update(input).digest("hex");
}

async function seed() {
  const conn = await mysql.createConnection(DB_CONFIG);

  console.log("Applying schema...");
  const schemaSql = fs.readFileSync(path.join(__dirname, "config", "schema.sql"), "utf8");
  await conn.query(schemaSql);

  console.log("Seeding users...");
  // NOTE (lab hint / CWE-615 style leftover): default admin credentials are
  // admin / admin123. Also duplicated as an HTML comment in the React app shell.
  const users = [
    ["admin", "admin@stridestyle.shop", md5("admin123"), 1],
    ["jordan", "jordan@example.com", md5("jordanpw"), 0],
    ["casey", "casey@example.com", md5("caseypw"), 0],
    ["sneakerhead_sam", "sam@example.com", md5("sam2024"), 0],
  ];
  for (const u of users) {
    await conn.query(
      "INSERT INTO users (username, email, password_hash, is_admin) VALUES (?, ?, ?, ?)",
      u
    );
  }

  console.log("Seeding categories...");
  const categories = [
    ["Running", "Lightweight performance shoes for the road and trail"],
    ["Sneakers", "Everyday casual sneakers and classic silhouettes"],
    ["Boots", "Rugged boots for hiking, work, and cold weather"],
    ["Formal", "Dress shoes, heels, and loafers for the office"],
    ["New Arrivals", "Our latest footwear drops"],
  ];
  const catIds = [];
  for (const c of categories) {
    const [res] = await conn.query(
      "INSERT INTO categories (name, description) VALUES (?, ?)",
      c
    );
    catIds.push(res.insertId);
  }

  console.log("Seeding products...");
  const products = [
    ["Velocity Runner", "velocity-runner", catIds[0], 89.99, "6,7,8,9,10,11,12",
     "Engineered Mesh, EVA Foam Sole",
     "Our lightest performance trainer yet, the Velocity Runner combines "
     + "breathable engineered mesh with responsive EVA foam cushioning for "
     + "daily miles that feel effortless.",
     "running-shoe-1.jpg", 85, 1],
    ["Marathon Pro X2", "marathon-pro-x2", catIds[0], 129.99, "7,8,9,10,11,12,13",
     "Knit Upper, Carbon Plate",
     "Built for race day, the Marathon Pro X2 features a full-length carbon "
     + "plate and a nitrogen-infused midsole for maximum energy return.",
     "running-shoe-2.jpg", 60, 1],
    ["Sprint Flash Red", "sprint-flash-red", catIds[0], 94.99, "6,7,8,9,10,11",
     "Breathable Mesh, Rubber Outsole",
     "Bold, fast, and grippy -- the Sprint Flash Red is built for track "
     + "workouts and tempo runs alike.",
     "running-shoe-red.jpg", 50, 0],
    ["Classic Court White", "classic-court-white", catIds[1], 74.99, "5,6,7,8,9,10,11,12",
     "Full-Grain Leather, Rubber Cupsole",
     "A timeless low-top silhouette in crisp white leather -- pairs with "
     + "everything from denim to tailored trousers.",
     "sneaker-white.jpg", 100, 1],
    ["Midnight Street Sneaker", "midnight-street-sneaker", catIds[1], 79.99, "6,7,8,9,10,11,12",
     "Synthetic Leather, Foam Midsole",
     "Sleek all-black street styling with a cushioned foam midsole for "
     + "all-day comfort on city sidewalks.",
     "sneaker-black.jpg", 70, 0],
    ["Heritage Canvas Low", "heritage-canvas-low", catIds[1], 54.99, "5,6,7,8,9,10,11",
     "Cotton Canvas, Vulcanized Rubber",
     "A retro-inspired canvas sneaker with a vulcanized rubber sole -- "
     + "casual, durable, and endlessly versatile.",
     "canvas-shoe.jpg", 90, 0],
    ["Trailblazer Hiking Boot", "trailblazer-hiking-boot", catIds[2], 149.99, "7,8,9,10,11,12,13",
     "Waterproof Nubuck, Vibram Outsole",
     "Conquer any trail with waterproof nubuck leather uppers and an "
     + "aggressive Vibram outsole for serious grip.",
     "hiking-boot.jpg", 45, 1],
    ["Heritage Leather Boot", "heritage-leather-boot", catIds[2], 159.99, "7,8,9,10,11,12",
     "Full-Grain Leather, Goodyear Welt",
     "Hand-finished full-grain leather with a Goodyear welt construction "
     + "built to be resoled for decades of wear.",
     "boot-leather.jpg", 35, 0],
    ["Meridian Heel Pump", "meridian-heel-pump", catIds[3], 99.99, "5,6,7,8,9,10",
     "Suede Upper, Cushioned Insole",
     "An elegant pointed-toe pump in soft suede, finished with a "
     + "cushioned insole for comfortable all-evening wear.",
     "heels-1.jpg", 40, 0],
    ["Oxford Business Loafer", "oxford-business-loafer", catIds[3], 119.99, "7,8,9,10,11,12",
     "Polished Leather, Leather Sole",
     "A refined slip-on loafer in polished leather -- boardroom-ready and "
     + "endlessly comfortable for long office days.",
     "loafers-1.jpg", 55, 1],
    ["Coastal Slide Sandal", "coastal-slide-sandal", catIds[4], 39.99, "6,7,8,9,10,11,12",
     "Molded EVA, Quick-Dry Straps",
     "Featherlight molded EVA sandals with quick-dry straps -- perfect for "
     + "the pool, beach, or a lazy Sunday.",
     "sandal-1.jpg", 75, 1],
    ["Cloud Comfort Slipper", "cloud-comfort-slipper", catIds[4], 34.99, "5,6,7,8,9,10,11",
     "Memory Foam, Plush Lining",
     "Sink into memory foam cushioning wrapped in a plush knit lining -- "
     + "the coziest way to end the day.",
     "slipper-1.jpg", 65, 0],
  ];
  const prodIds = [];
  for (const p of products) {
    const [res] = await conn.query(
      `INSERT INTO products
        (name, slug, category_id, price, sizes_available, material, description, image, stock, featured)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      p
    );
    prodIds.push(res.insertId);
  }

  console.log("Seeding reviews...");
  const reviews = [
    [prodIds[0], "jordan", 5, "Lightest running shoe I have ever owned. True to size."],
    [prodIds[0], "casey", 4, "Great cushioning, wish it came in more colors."],
    [prodIds[3], "sneakerhead_sam", 5, "Clean white leather, holds up great after cleaning."],
    [prodIds[6], "jordan", 5, "Took these on a 40-mile trail trip, zero blisters."],
    [prodIds[9], "casey", 5, "Perfect for the office, broke in within a day."],
    [prodIds[10], "sneakerhead_sam", 4, "Great poolside sandal, straps dry fast."],
  ];
  for (const r of reviews) {
    await conn.query(
      "INSERT INTO reviews (product_id, username, rating, comment) VALUES (?, ?, ?, ?)",
      r
    );
  }

  console.log("Seeding suppliers...");
  const suppliers = [
    ["Summit Leather Works", "summit-leather-supply.example.com", "orders@summit-leather-supply.example.com"],
    ["EVA Foam Solutions", "evafoam-supply.example.com", "sales@evafoam-supply.example.com"],
    ["Vibram Sole Direct", "vibram-direct.example.com", "info@vibram-direct.example.com"],
  ];
  for (const s of suppliers) {
    await conn.query(
      "INSERT INTO suppliers (name, hostname, contact_email) VALUES (?, ?, ?)",
      s
    );
  }

  console.log("Seeding sample order for jordan (user id 2)...");
  const [orderRes] = await conn.query(
    "INSERT INTO orders (user_id, total, status, shipping_address) VALUES (?, ?, ?, ?)",
    [2, 164.98, "shipped", "88 Birchwood Ave, Denver, CO 80202"]
  );
  await conn.query(
    "INSERT INTO order_items (order_id, product_id, quantity, price_each) VALUES (?, ?, ?, ?), (?, ?, ?, ?)",
    [orderRes.insertId, prodIds[0], 1, 89.99, orderRes.insertId, prodIds[9], 1, 74.99]
  );

  console.log("Seed complete.");
  console.log("Users: admin/admin123 (admin), jordan/jordanpw, casey/caseypw, sneakerhead_sam/sam2024");

  await conn.end();
}

seed().catch((err) => {
  console.error("Seed failed:", err);
  process.exit(1);
});
