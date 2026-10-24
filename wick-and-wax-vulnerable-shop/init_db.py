"""
init_db.py - Database initialization & seed data for Wick & Wax Co.
Run this once before starting the app: python3 init_db.py
"""
import sqlite3
import hashlib
import os

DB_PATH = os.path.join(os.path.dirname(__file__), "instance", "candleshop.db")


def md5_hash(password):
    # VULNERABLE: CWE-328 Reversible One-Way Hash
    # Passwords are hashed with unsalted MD5, a fast, reversible-by-lookup-table
    # algorithm. This is intentionally weak for training purposes.
    return hashlib.md5(password.encode()).hexdigest()


def build():
    os.makedirs(os.path.dirname(DB_PATH), exist_ok=True)
    if os.path.exists(DB_PATH):
        os.remove(DB_PATH)

    conn = sqlite3.connect(DB_PATH)
    cur = conn.cursor()

    cur.executescript(
        """
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            email TEXT NOT NULL,
            password_hash TEXT NOT NULL,
            is_admin INTEGER NOT NULL DEFAULT 0,
            avatar TEXT DEFAULT 'default-avatar.jpg',
            reset_token TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT
        );

        CREATE TABLE products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL,
            category_id INTEGER,
            price REAL NOT NULL,
            scent_notes TEXT,
            burn_time TEXT,
            description TEXT,
            image TEXT,
            stock INTEGER DEFAULT 50,
            featured INTEGER DEFAULT 0,
            FOREIGN KEY (category_id) REFERENCES categories (id)
        );

        CREATE TABLE reviews (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            username TEXT NOT NULL,
            rating INTEGER NOT NULL,
            comment TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products (id)
        );

        CREATE TABLE orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            total REAL NOT NULL,
            status TEXT DEFAULT 'processing',
            shipping_address TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users (id)
        );

        CREATE TABLE order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            quantity INTEGER NOT NULL,
            price_each REAL NOT NULL,
            FOREIGN KEY (order_id) REFERENCES orders (id),
            FOREIGN KEY (product_id) REFERENCES products (id)
        );

        CREATE TABLE suppliers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            hostname TEXT NOT NULL,
            contact_email TEXT
        );

        CREATE TABLE newsletter_subscribers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            subscribed_at TEXT DEFAULT CURRENT_TIMESTAMP
        );
        """
    )

    # --- Users ---
    # NOTE (lab hint / CWE-615 style leftover): default admin credentials are
    # admin / admin123 -- also duplicated as an HTML comment in templates.
    users = [
        ("admin", "admin@wickandwax.shop", md5_hash("admin123"), 1),
        ("alice", "alice@example.com", md5_hash("alicepw"), 0),
        ("bob", "bob@example.com", md5_hash("bobpw"), 0),
        ("candlelover22", "jenny@example.com", md5_hash("jenny2024"), 0),
    ]
    cur.executemany(
        "INSERT INTO users (username, email, password_hash, is_admin) VALUES (?, ?, ?, ?)",
        users,
    )

    # --- Categories ---
    categories = [
        ("Floral", "Soft, romantic floral fragrances"),
        ("Gourmand", "Sweet, warm, dessert-inspired scents"),
        ("Fresh & Clean", "Crisp, airy, linen and citrus notes"),
        ("Woody & Spiced", "Warm, earthy, cozy autumn scents"),
        ("Seasonal", "Limited-run seasonal favorites"),
    ]
    cur.executemany("INSERT INTO categories (name, description) VALUES (?, ?)", categories)

    # --- Products ---
    products = [
        ("Lavender Dream", "lavender-dream", 1, 24.00,
         "Lavender, Chamomile, Soft Musk", "45 hours",
         "Drift off into calm with our best-selling Lavender Dream candle. "
         "Hand-poured with French lavender buds and a whisper of chamomile, "
         "this soy-blend candle turns any room into a spa retreat.",
         "lavender-dream.jpg", 80, 1),
        ("Vanilla Bean Bliss", "vanilla-bean", 2, 22.00,
         "Madagascar Vanilla, Brown Sugar, Cream", "40 hours",
         "Rich Madagascar vanilla bean swirled with warm brown sugar and a "
         "touch of cream. Our coziest, most-gifted candle.",
         "vanilla-bean.jpg", 65, 1),
        ("Sea Salt & Driftwood", "sea-salt-driftwood", 3, 26.00,
         "Sea Salt, Driftwood, Ozone", "45 hours",
         "Coastal calm in a jar. Salty air, sun-bleached driftwood, and a "
         "hint of ocean breeze for the beach lover on your list.",
         "sea-salt-driftwood.jpg", 54, 0),
        ("Cinnamon Spice Market", "cinnamon-spice", 4, 23.00,
         "Cinnamon Bark, Clove, Orange Peel", "42 hours",
         "A warm hug of Ceylon cinnamon, clove and orange peel. Perfect for "
         "crisp autumn evenings by the fire.",
         "cinnamon-spice.jpg", 70, 1),
        ("Rose Garden", "rose-garden", 1, 25.00,
         "Bulgarian Rose, Peony, Green Leaves", "45 hours",
         "A dewy walk through a rose garden at dawn. Elegant, romantic, and "
         "never overpowering.",
         "rose-garden.jpg", 40, 0),
        ("Citrus Grove", "citrus-grove", 3, 21.00,
         "Blood Orange, Grapefruit, Mint", "38 hours",
         "Bright and zesty, Citrus Grove wakes up any room with juicy "
         "blood orange and a cooling mint finish.",
         "citrus-grove.jpg", 90, 0),
        ("Midnight Jasmine", "midnight-jasmine", 1, 27.00,
         "Night Jasmine, Sandalwood, Amber", "48 hours",
         "Sensual jasmine blooms layered over creamy sandalwood and warm "
         "amber. Our most luxurious blend.",
         "midnight-jasmine.jpg", 30, 1),
        ("Oakwood & Amber", "oakwood-amber", 4, 26.00,
         "Oakwood, Amber, Tobacco Leaf", "45 hours",
         "Deep, masculine and grounding -- aged oak, warm amber resin and a "
         "hint of tobacco leaf.",
         "oakwood-amber.jpg", 48, 0),
        ("Coconut Lime Cooler", "coconut-lime", 2, 22.00,
         "Coconut Milk, Lime Zest, Vanilla", "40 hours",
         "Transport yourself to a beachside cabana with creamy coconut and "
         "bright lime zest.",
         "coconut-lime.jpg", 60, 0),
        ("Fresh Linen", "fresh-linen", 3, 20.00,
         "Cotton, White Musk, Aloe", "42 hours",
         "The smell of line-dried sheets on a sunny day. Clean, soft, and "
         "endlessly comforting.",
         "fresh-linen.jpg", 100, 0),
        ("Pumpkin Spice Harvest", "pumpkin-spice", 5, 24.00,
         "Pumpkin, Nutmeg, Maple", "45 hours",
         "Our seasonal favorite returns! Roasted pumpkin, warm nutmeg and a "
         "drizzle of maple.",
         "pumpkin-spice.jpg", 75, 1),
        ("Eucalyptus Mint Spa", "eucalyptus-mint", 3, 23.00,
         "Eucalyptus, Spearmint, Green Tea", "42 hours",
         "A spa day at home. Cool eucalyptus and spearmint with a green tea "
         "base note.",
         "eucalyptus-mint.jpg", 55, 0),
    ]
    cur.executemany(
        """INSERT INTO products
           (name, slug, category_id, price, scent_notes, burn_time, description, image, stock, featured)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)""",
        products,
    )

    # --- Reviews (seed, non-malicious) ---
    reviews = [
        (1, "alice", 5, "My absolute favorite! The lavender is so calming."),
        (1, "bob", 4, "Great scent throw, lasted the whole evening."),
        (2, "candlelover22", 5, "Smells like a bakery. Obsessed."),
        (4, "alice", 5, "Perfect for fall. Bought three!"),
        (7, "bob", 5, "Luxurious and long-lasting."),
        (11, "candlelover22", 4, "Bring this back every year please!"),
    ]
    cur.executemany(
        "INSERT INTO reviews (product_id, username, rating, comment) VALUES (?, ?, ?, ?)",
        reviews,
    )

    # --- Suppliers (used by admin "ping supplier" tool -> command injection) ---
    suppliers = [
        ("WaxWorks Wholesale", "waxworks-supply.example.com", "orders@waxworks-supply.example.com"),
        ("Fragrance Oil Co.", "fragranceoil-supply.example.com", "sales@fragranceoil-supply.example.com"),
        ("GlassJar Direct", "glassjar-direct.example.com", "info@glassjar-direct.example.com"),
    ]
    cur.executemany(
        "INSERT INTO suppliers (name, hostname, contact_email) VALUES (?, ?, ?)",
        suppliers,
    )

    # --- Sample order for alice (id=2) ---
    cur.execute(
        "INSERT INTO orders (user_id, total, status, shipping_address) VALUES (?, ?, ?, ?)",
        (2, 46.00, "shipped", "12 Maple Street, Portland, OR 97201"),
    )
    order_id = cur.lastrowid
    cur.executemany(
        "INSERT INTO order_items (order_id, product_id, quantity, price_each) VALUES (?, ?, ?, ?)",
        [(order_id, 1, 1, 24.00), (order_id, 10, 1, 20.00)],
    )

    conn.commit()
    conn.close()
    print(f"Database initialized at {DB_PATH}")
    print("Seeded users: admin/admin123 (admin), alice/alicepw, bob/bobpw, candlelover22/jenny2024")


if __name__ == "__main__":
    build()
