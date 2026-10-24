"""Initialize school.db with schema and seed data."""
import sqlite3, hashlib, os

DB = os.path.join(os.path.dirname(__file__), "school.db")

def md5(p): return hashlib.md5(p.encode()).hexdigest()

def main():
    if os.path.exists(DB):
        os.remove(DB)
    c = sqlite3.connect(DB)
    c.executescript("""
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        password_hash TEXT,
        full_name TEXT,
        role TEXT DEFAULT 'student'
    );
    CREATE TABLE students (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        grade TEXT,
        bio TEXT
    );
    CREATE TABLE courses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT,
        code TEXT
    );
    """)
    users = [
        ("admin", md5("Admin@123"), "System Administrator", "admin"),
        ("teacher", md5("Teacher@123"), "Jane Doe", "teacher"),
        ("student", md5("Student@123"), "John Smith", "student"),
    ]
    c.executemany("INSERT INTO users (username,password_hash,full_name,role) VALUES (?,?,?,?)", users)

    students = [
        ("Alice Johnson", "A", "Class representative and debate team lead."),
        ("Bob Martinez", "B", "Enjoys robotics and mathematics."),
        ("Carol White", "A", "Volunteers at the science club."),
        ("David Lee", "C", "Football team goalkeeper."),
        ("Emma Brown", "B", "Editor of the school newspaper."),
    ]
    c.executemany("INSERT INTO students (name,grade,bio) VALUES (?,?,?)", students)

    courses = [
        ("Introduction to Computer Science", "CS101"),
        ("Algebra II", "MA201"),
        ("World History", "HI110"),
        ("Organic Chemistry", "CH305"),
    ]
    c.executemany("INSERT INTO courses (title,code) VALUES (?,?)", courses)

    c.commit(); c.close()
    print("Seeded", DB)

if __name__ == "__main__":
    main()
