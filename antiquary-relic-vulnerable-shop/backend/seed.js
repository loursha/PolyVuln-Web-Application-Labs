/**
 * seed.js -- The Antiquary & Relic Co. database seed data
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
  user: process.env.DB_USER || "antiques_app",
  password: process.env.DB_PASSWORD || "AntiquesApp2024!",
  database: process.env.DB_NAME || "antiques_shop",
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
    ["admin", "admin@antiquaryrelic.shop", md5("admin123"), 1],
    ["eleanor", "eleanor@example.com", md5("eleanorpw"), 0],
    ["marcus", "marcus@example.com", md5("marcuspw"), 0],
    ["curio_hunter", "hunter@example.com", md5("hunter2024"), 0],
  ];
  for (const u of users) {
    await conn.query(
      "INSERT INTO users (username, email, password_hash, is_admin) VALUES (?, ?, ?, ?)",
      u
    );
  }

  console.log("Seeding eras...");
  const eras = [
    ["Victorian", "Ornate, hand-crafted pieces from the 19th-century British Empire"],
    ["Art Deco", "Bold geometric lines and luxury materials from the 1920s-30s"],
    ["Georgian", "Refined elegance from the 18th and early 19th centuries"],
    ["Mid-Century", "Clean, functional design from the 1940s-60s"],
    ["Newly Catalogued", "Recently authenticated arrivals to our showroom"],
  ];
  const eraIds = [];
  for (const e of eras) {
    const [res] = await conn.query("INSERT INTO eras (name, description) VALUES (?, ?)", e);
    eraIds.push(res.insertId);
  }

  console.log("Seeding artifacts...");
  const artifacts = [
    ["Victorian Mantel Clock", "victorian-mantel-clock", eraIds[0], 1250.00,
     "Manchester, England, c. 1875", "Excellent -- fully restored",
     "An exquisite hand-carved mahogany mantel clock with an eight-day "
     + "brass movement, fully serviced and keeping accurate time.",
     "victorian-clock.jpg", 1, 1],
    ["Hand-Painted Porcelain Vase", "porcelain-vase", eraIds[0], 680.00,
     "Staffordshire, England, c. 1890", "Very good -- minor glaze crazing",
     "A tall hand-painted porcelain vase featuring gilt trim and a "
     + "pastoral scene, signed by the original decorator.",
     "antique-vase.jpg", 2, 1],
    ["Brass Ship's Compass", "brass-ships-compass", eraIds[0], 420.00,
     "Bristol, England, c. 1880", "Good -- working, light tarnish",
     "A gimbal-mounted brass ship's compass recovered from a decommissioned "
     + "merchant vessel, fully functional with original binnacle.",
     "brass-compass.jpg", 1, 0],
    ["Art Deco Typewriter", "art-deco-typewriter", eraIds[1], 340.00,
     "Turin, Italy, c. 1928", "Good -- fully functional",
     "A striking black-and-chrome Art Deco typewriter with QWERTY "
     + "keyboard, recently serviced and in full working order.",
     "vintage-typewriter.jpg", 1, 1],
    ["Antique Terrestrial Globe", "terrestrial-globe", eraIds[2], 890.00,
     "London, England, c. 1810", "Fair -- period-appropriate wear",
     "A hand-gored terrestrial globe on a turned mahogany stand, "
     + "depicting pre-unification European borders.",
     "old-globe.jpg", 1, 0],
    ["Gilt-Framed Wall Mirror", "gilt-framed-mirror", eraIds[2], 560.00,
     "Paris, France, c. 1820", "Very good -- original glass",
     "A Georgian giltwood wall mirror with its original mercury-backed "
     + "glass and hand-carved acanthus leaf detailing.",
     "antique-mirror.jpg", 1, 1],
    ["Bisque Porcelain Doll", "bisque-porcelain-doll", eraIds[0], 310.00,
     "Thuringia, Germany, c. 1900", "Good -- original clothing",
     "A German bisque porcelain doll with sleeping glass eyes and an "
     + "original hand-stitched Victorian dress.",
     "porcelain-doll.jpg", 1, 0],
    ["Sterling Silver Candelabra", "silver-candelabra", eraIds[2], 1450.00,
     "Sheffield, England, c. 1795", "Excellent -- fully hallmarked",
     "A five-branch sterling silver candelabra bearing full Sheffield "
     + "assay hallmarks, recently polished and re-silvered.",
     "silver-candelabra.jpg", 1, 1],
    ["18k Gold Pocket Watch", "gold-pocket-watch", eraIds[0], 2100.00,
     "Geneva, Switzerland, c. 1885", "Excellent -- recently serviced",
     "An 18-karat gold open-face pocket watch with a jeweled Swiss "
     + "movement, complete with its original chain and fob.",
     "antique-pocketwatch.jpg", 1, 1],
    ["Carved Oak Blanket Chest", "oak-blanket-chest", eraIds[2], 780.00,
     "Yorkshire, England, c. 1790", "Good -- structurally sound",
     "A carved oak blanket chest with wrought iron strap hinges and "
     + "an internal candle box, showing honest period wear.",
     "wooden-chest.jpg", 1, 0],
    ["Framed Oil Landscape", "oil-landscape-painting", eraIds[3], 1980.00,
     "New York, USA, c. 1955", "Very good -- recently reframed",
     "A mid-century oil-on-canvas landscape in a period walnut frame, "
     + "signed and dated by the artist in the lower corner.",
     "oil-painting.jpg", 1, 1],
    ["Hand-Thrown Ceramic Urn", "ceramic-urn", eraIds[4], 240.00,
     "Newly catalogued -- origin under review", "Very good -- hairline crack repaired",
     "A recently acquired hand-thrown ceramic urn awaiting full "
     + "provenance research; early indications suggest a 19th-century origin.",
     "ceramic-urn.jpg", 1, 0],
  ];
  const artifactIds = [];
  for (const a of artifacts) {
    const [res] = await conn.query(
      `INSERT INTO artifacts
        (name, slug, era_id, price, origin, condition_code, description, image, stock, featured)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      a
    );
    artifactIds.push(res.insertId);
  }

  console.log("Seeding provenance notes...");
  const notes = [
    [artifactIds[0], "eleanor", "Purchased at auction from the estate of a Manchester textile family, 1962."],
    [artifactIds[0], "marcus", "Confirmed against the original maker's ledger held by the Clockmakers' Company."],
    [artifactIds[3], "curio_hunter", "Matches the serial number range documented in the 1928 Turin factory catalog."],
    [artifactIds[7], "eleanor", "Hallmarks verified against the Sheffield Assay Office archive."],
    [artifactIds[8], "marcus", "Movement stamped with the original Geneva workshop mark."],
    [artifactIds[10], "curio_hunter", "Signature cross-referenced with the artist's known exhibition catalog."],
  ];
  for (const n of notes) {
    await conn.query(
      "INSERT INTO provenance_notes (artifact_id, username, note) VALUES (?, ?, ?)",
      n
    );
  }

  console.log("Seeding authenticators...");
  const authenticators = [
    ["Guild of Horological Authenticators", "guild-horology-verify.example.com", "verify@guild-horology.example.com"],
    ["National Silver Assay Registry", "silver-assay-registry.example.com", "verify@silver-assay-registry.example.com"],
    ["Fine Art Provenance Bureau", "fine-art-provenance.example.com", "verify@fine-art-provenance.example.com"],
  ];
  for (const a of authenticators) {
    await conn.query(
      "INSERT INTO authenticators (name, hostname, contact_email) VALUES (?, ?, ?)",
      a
    );
  }

  console.log("Seeding sample order for eleanor (user id 2)...");
  const [orderRes] = await conn.query(
    "INSERT INTO orders (user_id, total, status, shipping_address) VALUES (?, ?, ?, ?)",
    [2, 1930.00, "shipped", "14 Hollow Lane, Bath, BA1 2QF, United Kingdom"]
  );
  await conn.query(
    "INSERT INTO order_items (order_id, artifact_id, quantity, price_each) VALUES (?, ?, ?, ?), (?, ?, ?, ?)",
    [orderRes.insertId, artifactIds[1], 1, 680.00, orderRes.insertId, artifactIds[3], 1, 340.00]
  );

  conn.close ? await conn.close() : await conn.end();
  console.log("Seed complete.");
  console.log("Users: admin/admin123 (admin), eleanor/eleanorpw, marcus/marcuspw, curio_hunter/hunter2024");
}

seed().catch((err) => {
  console.error("Seed failed:", err);
  process.exit(1);
});
