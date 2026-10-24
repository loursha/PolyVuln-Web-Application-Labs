"""
init_db.py - Database initialization & seed data for Little Cubs Toy Co.
Run this once before starting the app: python3 init_db.py
"""
import sqlite3
import hashlib
import os

DB_PATH = os.path.join(os.path.dirname(__file__), "instance", "toyshop.db")


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
            material TEXT,
            age_range TEXT,
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
        ("admin", "admin@littlecubs.shop", md5_hash("admin123"), 1),
        ("mia", "mia@example.com", md5_hash("miapw"), 0),
        ("noah", "noah@example.com", md5_hash("noahpw"), 0),
        ("toybox_tara", "tara@example.com", md5_hash("tara2024"), 0),
    ]
    cur.executemany(
        "INSERT INTO users (username, email, password_hash, is_admin) VALUES (?, ?, ?, ?)",
        users,
    )

    # --- Categories ---
    categories = [
        ("Cuddly Friends", "Classic soft plush animals for hugging and snuggling"),
        ("Baby Safe", "Extra-soft, machine-washable toys safe from birth"),
        ("Fantasy Friends", "Unicorns, dragons and other magical plush pals"),
        ("Farm & Jungle", "Barnyard and jungle animal plushies"),
        ("New Arrivals", "Our latest and most-loved plush additions"),
    ]
    cur.executemany("INSERT INTO categories (name, description) VALUES (?, ?)", categories)

    # --- Products ---
    products = [
        ("Classic Teddy Bear", "teddy-bear-classic", 1, 19.99,
         "100% Organic Cotton, Hypoallergenic Fill", "0+ months",
         "Our signature Classic Teddy Bear is hand-stitched from the softest "
         "organic cotton and stuffed with hypoallergenic fill. A timeless "
         "first friend for every nursery.",
         "teddy-bear-classic.jpg", 90, 1),
        ("Bouncy Bunny Plush", "bunny-plush", 1, 17.99,
         "Plush Minky Fabric, Bean-Free Fill", "0+ months",
         "Long floppy ears and the fluffiest tail -- Bouncy Bunny is an "
         "instant best friend for nap time and playtime alike.",
         "bunny-plush.jpg", 75, 1),
        ("Ellie the Elephant", "elephant-plush", 4, 21.99,
         "Soft Chenille, Recycled Fiber Fill", "3+ months",
         "Ellie's oversized ears and gentle trunk make her perfect for "
         "little hands to grab and squeeze during tummy time.",
         "elephant-plush.jpg", 60, 0),
        ("Sparkle the Unicorn", "unicorn-plush", 3, 24.99,
         "Rainbow Sherpa Fabric, Soft Fill", "6+ months",
         "With a shimmering rainbow mane and tail, Sparkle brings a little "
         "bit of magic to every bedtime story.",
         "unicorn-plush.jpg", 55, 1),
        ("Puppy Pal", "puppy-plush", 1, 18.99,
         "Plush Fleece, Machine Washable", "0+ months",
         "Floppy-eared and always smiling, Puppy Pal is the loyal companion "
         "every toddler needs on car rides and quiet afternoons.",
         "puppy-plush.jpg", 70, 0),
        ("Gigi the Giraffe", "giraffe-plush", 4, 22.99,
         "Soft Minky, Extra-Long Neck for Hugging", "3+ months",
         "Gigi's extra-long, huggable neck and gentle spotted pattern make "
         "her a favorite for snuggling and stroller rides.",
         "giraffe-plush.jpg", 45, 0),
        ("Leo the Lion Cub", "lion-plush", 4, 20.99,
         "Soft Plush Fur, Squeaker-Free", "3+ months",
         "Leo may have a fluffy mane, but he's the gentlest cub in the "
         "jungle -- perfect for brave little explorers.",
         "lion-plush.jpg", 50, 0),
        ("Panda Bear Buddy", "panda-plush", 1, 19.99,
         "Bamboo-Soft Plush, Eco Fill", "0+ months",
         "Black-and-white and irresistibly huggable, Panda Bear Buddy is "
         "stuffed with eco-friendly recycled fill.",
         "panda-plush.jpg", 65, 1),
        ("Ollie the Owl", "owl-plush", 3, 18.99,
         "Soft Velour Wings, Plush Belly", "3+ months",
         "With big embroidered eyes and velvety-soft wings, Ollie watches "
         "over sweet dreams every night.",
         "owl-plush.jpg", 40, 0),
        ("Felix the Fox", "fox-plush", 4, 20.99,
         "Plush Faux Fur, Soft Fill", "3+ months",
         "Felix's bushy tail and rosy cheeks make him an instant favorite "
         "for imaginative forest adventures.",
         "fox-plush.jpg", 48, 0),
        ("Koala Cuddler", "koala-plush", 2, 19.99,
         "Ultra-Soft Minky, Baby-Safe Stitching", "0+ months",
         "Designed with reinforced baby-safe stitching, Koala Cuddler is "
         "gentle enough for the newest members of the family.",
         "koala-plush.jpg", 80, 1),
        ("Baby Dino", "dino-plush", 5, 21.99,
         "Soft Spiky Plush, Squish-Safe Spikes", "6+ months",
         "Baby Dino's spikes are extra-soft and squishy -- roar-some fun "
         "with zero rough edges.",
         "dino-plush.jpg", 58, 1),
    ]
    cur.executemany(
        """INSERT INTO products
           (name, slug, category_id, price, material, age_range, description, image, stock, featured)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)""",
        products,
    )

    # --- Reviews (seed, non-malicious) ---
    reviews = [
        (1, "mia", 5, "My daughter sleeps with this every night. So soft!"),
        (1, "noah", 5, "Perfect baby shower gift, washes beautifully."),
        (4, "toybox_tara", 5, "The mane is gorgeous, my son won't put it down."),
        (8, "mia", 4, "Adorable and great quality stitching."),
        (11, "noah", 5, "Safe for our newborn, super gentle fabric."),
        (12, "toybox_tara", 4, "Cutest dino ever, spikes are totally soft."),
    ]
    cur.executemany(
        "INSERT INTO reviews (product_id, username, rating, comment) VALUES (?, ?, ?, ?)",
        reviews,
    )

    # --- Suppliers (used by admin "ping supplier" tool -> command injection) ---
    suppliers = [
        ("PlushWorks Wholesale", "plushworks-supply.example.com", "orders@plushworks-supply.example.com"),
        ("SoftFill Textiles Co.", "softfill-supply.example.com", "sales@softfill-supply.example.com"),
        ("CuddleCraft Direct", "cuddlecraft-direct.example.com", "info@cuddlecraft-direct.example.com"),
    ]
    cur.executemany(
        "INSERT INTO suppliers (name, hostname, contact_email) VALUES (?, ?, ?)",
        suppliers,
    )

    # --- Sample order for mia (id=2) ---
    cur.execute(
        "INSERT INTO orders (user_id, total, status, shipping_address) VALUES (?, ?, ?, ?)",
        (2, 41.98, "shipped", "45 Oakwood Lane, Austin, TX 78701"),
    )
    order_id = cur.lastrowid
    cur.executemany(
        "INSERT INTO order_items (order_id, product_id, quantity, price_each) VALUES (?, ?, ?, ?)",
        [(order_id, 1, 1, 19.99), (order_id, 11, 1, 19.99)],
    )

    conn.commit()
    conn.close()
    print(f"Database initialized at {DB_PATH}")
    print("Seeded users: admin/admin123 (admin), mia/miapw, noah/noahpw, toybox_tara/tara2024")


if __name__ == "__main__":
    build()
