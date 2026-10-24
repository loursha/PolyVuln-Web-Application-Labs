"""
Database initialization + seed data for Northwind HR Portal.
Run automatically on first app start, or manually: python init_db.py
"""
import sqlite3
import hashlib
import os

DB = os.environ.get("DATABASE", "hrportal.db")


def md5(pw):
    # CWE-328: Reversible / weak one-way hash (unsalted MD5)
    return hashlib.md5(pw.encode()).hexdigest()


def init_db():
    if os.path.exists(DB):
        os.remove(DB)
    conn = sqlite3.connect(DB)
    c = conn.cursor()

    c.executescript(
        """
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,          -- MD5 (weak)
        full_name TEXT,
        email TEXT,
        role TEXT NOT NULL,              -- admin | hr | employee
        department TEXT,
        title TEXT,
        salary INTEGER,
        phone TEXT,
        bio TEXT,                        -- stored XSS sink
        avatar TEXT,
        security_question TEXT,
        security_answer TEXT,            -- cleartext (weak recovery)
        active INTEGER DEFAULT 1
    );

    CREATE TABLE leave_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        leave_type TEXT,
        start_date TEXT,
        end_date TEXT,
        reason TEXT,                     -- stored XSS sink
        status TEXT DEFAULT 'Pending',
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE announcements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT,
        body TEXT,                       -- stored XSS sink
        author TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE payroll (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        period TEXT,
        gross INTEGER,
        tax INTEGER,
        net INTEGER,
        notes TEXT
    );

    CREATE TABLE documents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        filename TEXT,
        original_name TEXT,
        uploaded_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    """
    )

    users = [
        # username, pass, name, email, role, dept, title, salary, phone, bio, sec_q, sec_a
        ("admin", "Admin@123", "Alice Admin", "admin@northwind-hr.local", "admin",
         "IT", "System Administrator", 145000, "555-0100",
         "HR portal super administrator.", "First pet name?", "rex"),
        ("hr.manager", "Hr@2024", "Helen Rivers", "helen@northwind-hr.local", "hr",
         "Human Resources", "HR Manager", 98000, "555-0111",
         "Leading the people team.", "City of birth?", "denver"),
        ("hr.recruiter", "Recruit1", "Raj Patel", "raj@northwind-hr.local", "hr",
         "Human Resources", "Talent Recruiter", 72000, "555-0112",
         "Hiring great people.", "Mother maiden name?", "singh"),
        ("j.doe", "Password1", "John Doe", "john@northwind-hr.local", "employee",
         "Engineering", "Software Engineer", 89000, "555-0201",
         "Backend developer.", "Favorite color?", "blue"),
        ("m.chan", "Welcome1", "Mia Chan", "mia@northwind-hr.local", "employee",
         "Sales", "Account Executive", 76000, "555-0202",
         "Closing deals.", "First school?", "lincoln"),
        ("s.gupta", "Sunny123", "Sam Gupta", "sam@northwind-hr.local", "employee",
         "Marketing", "Content Strategist", 68000, "555-0203",
         "Words and campaigns.", "Favorite food?", "pizza"),
    ]
    for u in users:
        c.execute(
            """INSERT INTO users
            (username,password,full_name,email,role,department,title,salary,phone,bio,
             security_question,security_answer)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)""",
            (u[0], md5(u[1]), u[2], u[3], u[4], u[5], u[6], u[7], u[8], u[9], u[10], u[11]),
        )

    c.execute(
        "INSERT INTO announcements (title,body,author) VALUES (?,?,?)",
        ("Welcome to Northwind HR", "Q3 performance reviews open next week.", "Helen Rivers"),
    )
    c.execute(
        "INSERT INTO announcements (title,body,author) VALUES (?,?,?)",
        ("Holiday Schedule", "Office closed Dec 24-26. Plan your leave.", "Alice Admin"),
    )

    c.execute(
        "INSERT INTO leave_requests (user_id,leave_type,start_date,end_date,reason,status) VALUES (?,?,?,?,?,?)",
        (4, "Vacation", "2024-08-01", "2024-08-05", "Family trip", "Approved"),
    )
    c.execute(
        "INSERT INTO leave_requests (user_id,leave_type,start_date,end_date,reason,status) VALUES (?,?,?,?,?,?)",
        (5, "Sick", "2024-07-10", "2024-07-11", "Flu", "Pending"),
    )

    payroll = [
        (1, "2024-06", 12083, 3625, 8458, "Admin salary"),
        (2, "2024-06", 8166, 2450, 5716, "HR manager"),
        (4, "2024-06", 7416, 2225, 5191, "Engineering"),
        (5, "2024-06", 6333, 1900, 4433, "Sales"),
    ]
    for p in payroll:
        c.execute(
            "INSERT INTO payroll (user_id,period,gross,tax,net,notes) VALUES (?,?,?,?,?,?)", p
        )

    conn.commit()
    conn.close()
    print(f"[+] Database initialized: {DB}")
    print("[+] Seed accounts:")
    print("    admin / Admin@123      (admin)")
    print("    hr.manager / Hr@2024   (hr)")
    print("    j.doe / Password1      (employee)")


if __name__ == "__main__":
    init_db()
