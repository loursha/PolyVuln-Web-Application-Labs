# Fête de Cadre — Penetration Test Brief

You have been engaged to test **Fête de Cadre**, an independent Providence photo-frame boutique, before it launches. The shop sells ready-made and custom frames, lets customers track orders and request bespoke framing, and has a back office used by framers and the owner.

## Scope
- **In scope:** the web application at the URL your instructor gives you, all of its pages and APIs, and anything you can reach *through* the application.
- **Out of scope:** the host machine's other services, other students' machines, denial-of-service, and anything outside the lab network.

## Starting point
You have a normal customer account:

| Username | Password |
|---|---|
| `devon` | `password1` |

You can also register your own accounts. No staff or admin credentials are provided.

## Objectives
The owner has heard there are around 25 issues. Find as many as you can.

1. Map the application: every page, form, API endpoint, cookie and client-side script.
2. Find and prove each vulnerability with a minimal, non-destructive proof of concept.
3. Classify each finding with its OWASP Top 10 (2025) category and CWE.
4. Chain findings to reach the workshop back office and then the admin dashboard.
5. Capture the flags (`FLAG{...}`) hidden behind several issues.
6. Recommend a fix for each finding.

### Questions to guide you
- What can an anonymous visitor learn before signing in?
- Which features make the *server* fetch, read, run or calculate something on your behalf?
- Which values does the server trust that you control (fields, cookies, tokens)?
- What happens when you send input the developers didn't expect?
- How does the shop decide who you are, and what you're allowed to do?

## Rules of engagement
- Keep proofs of concept harmless: prefer `confirm()`, `id`, or reading a record over changing or destroying data.
- Don't lock out or break accounts other students rely on. If the shop starts misbehaving for everyone, tell your instructor.
- Record every request and response you use as evidence.

## Reporting
For each finding include: title, OWASP category and CWE, affected URL and parameter, steps to reproduce, evidence (request/response or screenshot), impact, and remediation. Finish with an attack-chain diagram showing how you escalated from customer to admin.

## Suggested tools
Burp Suite or OWASP ZAP, browser DevTools, sqlmap, hashcat or John the Ripper, ffuf or gobuster, Retire.js, Wireshark, curl, and a small local web server for proof-of-concept pages.
