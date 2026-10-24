<div align="center">

# 🔐 Security Policy

### PolyVuln: Deliberately Vulnerable Web Application Labs

</div>

---

> [!CAUTION]
> **PolyVuln is insecure by design.** Every lab in this repository contains intentional vulnerabilities for security training. This policy explains what *should* and *should not* be reported, and how to run the labs safely.

---

## 📑 Table of Contents

- [Understanding This Project](#-understanding-this-project)
- [What Is In Scope](#-what-is-in-scope)
- [What Is Out of Scope](#-what-is-out-of-scope)
- [Supported Versions](#-supported-versions)
- [How to Report a Vulnerability](#-how-to-report-a-vulnerability)
- [What to Include in Your Report](#-what-to-include-in-your-report)
- [Response Timeline](#%EF%B8%8F-response-timeline)
- [Disclosure Policy](#-disclosure-policy)
- [Safe Harbor](#%EF%B8%8F-safe-harbor)
- [Safe Deployment Guidelines](#-safe-deployment-guidelines)
- [Credentials and Secrets in This Repository](#-credentials-and-secrets-in-this-repository)
- [Antivirus and Security Tool Alerts](#-antivirus-and-security-tool-alerts)
- [Contact](#-contact)

---

## 📖 Understanding This Project

Most security policies ask you to report every vulnerability you find. **This one is different.**

PolyVuln exists to teach people how to find, exploit, and fix web application vulnerabilities. Flaws such as SQL injection, cross-site scripting, SSRF, command injection, broken authentication, and weak cryptography are **deliberately built into the labs** and documented in each lab's own README.

What matters to us are **unintended** weaknesses: problems that let a lab escape its isolated environment, harm the person running it, or put users of this repository at risk outside the training context.

---

## ✅ What Is In Scope

Please report issues like these:

| Category | Examples |
|---|---|
| **Containment escape** | A lab can break out of its Docker container or Kubernetes namespace and affect the host system |
| **Unintended network exposure** | A lab binds to `0.0.0.0` or opens ports to the network despite documentation saying it is `127.0.0.1` only |
| **Host impact** | Setup, install, or reset scripts that delete, modify, or expose files outside the project folder |
| **Supply chain compromise** | A dependency, Docker base image, or vendored file in the repo that contains genuinely malicious code (not just an intentionally outdated version) |
| **Real secrets** | A real, working credential, API key, token, or private key accidentally committed (as opposed to fake lab values) |
| **Real personal data** | Any real person's personal information in seed data, logs, images, or documents |
| **Repository and CI security** | Weaknesses in GitHub Actions workflows, release artifacts, or repo settings that could let someone tamper with the project |
| **Undocumented dangerous behaviour** | A lab behaves in a way that could cause real-world harm and is not part of its documented vulnerability matrix |

---

## ❌ What Is Out of Scope

Please **do not** report the following. They are intentional and part of the training design:

- Any vulnerability listed in a lab's vulnerability matrix or README
- Vulnerabilities mapped to OWASP Top 10:2025 categories inside the lab applications
- Outdated or vulnerable libraries pinned on purpose (e.g. old jQuery or log4j versions used for supply-chain exercises)
- Default, weak, or hard-coded credentials documented for lab use
- Fake `.env` files, demo API keys, sample database backups, and seeded logs placed for training exercises
- Missing security headers, verbose error messages, or debug endpoints inside the labs
- Findings from automated scanners run against the labs themselves
- Issues that only occur when a lab is deliberately exposed to the internet against our documented guidance

> [!TIP]
> Not sure whether something is intentional? Check the lab's README first. If it's still unclear, report it anyway. We'd rather receive a report we didn't need than miss one we did.

---

## 📦 Supported Versions

| Version | Supported |
|---|---|
| `main` branch (latest) | ✅ Yes |
| Tagged releases (latest) | ✅ Yes |
| Older releases | ❌ No: please update to the latest version |
| Forks and modified copies | ❌ No: contact the fork maintainer |

---

## 📮 How to Report a Vulnerability

**Please do not open a public GitHub issue for unintended security problems.** Public issues can expose other users before a fix is available.

Use one of these private channels instead:

### Option 1: GitHub Private Vulnerability Reporting (preferred)

1. Go to the repository's **[Security tab](https://github.com/loursha/PolyVuln-Web-Application-Labs/security)**.
2. Click **Report a vulnerability**.
3. Fill in the form and submit.

### Option 2: Email

Send your report to **[lourdhuraj143@gmail.com](mailto:lourdhuraj143@gmail.com)** with the subject line:

```text
[PolyVuln Security] <short description of the issue>
```

---

## 📝 What to Include in Your Report

A good report helps us reproduce and fix the issue quickly. Please include:

1. **Summary**: a short description of the problem.
2. **Affected lab or component**: folder name, file path, and commit hash or release version.
3. **Environment**: OS, Docker / Kubernetes / VM, and relevant versions.
4. **Steps to reproduce**: clear, numbered steps.
5. **Impact**: what an attacker or faulty setup could do, and why it's not an intended lab vulnerability.
6. **Evidence**: logs, screenshots, or command output (redact any real personal data).
7. **Suggested fix** (optional): if you have ideas, we'd love to hear them.
8. **Credit preference**: how you'd like to be credited, or whether you'd prefer to stay anonymous.

---

## ⏱️ Response Timeline

This is a community project maintained in personal time, so these are targets rather than guarantees:

| Stage | Target |
|---|---|
| Acknowledgement of your report | Within **3 business days** |
| Initial assessment (in scope or not) | Within **7 days** |
| Fix for critical issues (e.g. containment escape, real secrets) | Within **14 days** |
| Fix for other in-scope issues | Within **30 days** |
| Status updates | At least every **7 days** until resolved |

---

## 📢 Disclosure Policy

We follow **coordinated disclosure**:

1. You report the issue privately.
2. We confirm, investigate, and develop a fix.
3. We release the fix and publish a GitHub Security Advisory where appropriate.
4. You're credited (if you wish) in the advisory and in [CONTRIBUTORS.md](CONTRIBUTORS.md).
5. Public discussion is welcome after the fix is released, or after **90 days** from your report, whichever comes first.

If a real secret has been exposed, we will revoke or rotate it immediately, before any public disclosure.

---

## 🛡️ Safe Harbor

We support good-faith security research. If you follow this policy, we will:

- Consider your research authorized for the purposes of this project
- Not pursue or support legal action against you for your research
- Work with you to understand and resolve the issue quickly
- Credit you publicly if you wish

**Good-faith research means:**

- ✅ Testing only on your own local copy of the labs
- ✅ Reporting privately and giving us reasonable time to fix
- ✅ Avoiding privacy violations, data destruction, and service disruption
- ❌ Not testing against systems you don't own, including other people's deployments of PolyVuln
- ❌ Not using techniques from these labs against any real-world system without written permission

Safe harbor applies only to this repository. It does not authorize testing of any third-party service, including GitHub itself.

---

## 🧱 Safe Deployment Guidelines

The labs are safe to use **only when properly isolated**. Always follow these rules:

| ✅ Do | ❌ Don't |
|---|---|
| Run labs inside a dedicated VM with host-only or NAT networking | Run labs on your everyday work or personal machine |
| Keep services bound to `127.0.0.1` | Bind services to `0.0.0.0` or forward ports through your router |
| Use a disposable local Kubernetes cluster (Minikube, Kind, k3s) | Deploy to shared, staging, or production clusters |
| Take a VM snapshot before starting | Store real files, credentials, or data on the lab machine |
| Reset or destroy the lab after each session | Leave labs running unattended |
| Use only the provided synthetic data | Load real customer, employee, or student data |
| Shut the lab down when not in use | Deploy to a cloud instance with a public IP |

> [!WARNING]
> Several labs include intentional **command injection** and **SSRF** vulnerabilities. If a lab is reachable from a network, anyone on that network can potentially execute commands on the machine running it or reach other internal systems through it.

---

## 🔑 Credentials and Secrets in This Repository

All credentials, API keys, tokens, `.env` files, database backups, and log files in this repository are **fake and created solely for training**. They:

- Do not grant access to any real system
- Are intentionally weak or exposed to support lab exercises
- Must **never** be reused anywhere else

If you find a value that looks like a **real**, working secret (for example, a live cloud key or a personal access token), report it immediately using the private channels above.

---

## 🦠 Antivirus and Security Tool Alerts

Antivirus software, EDR agents, and GitHub secret scanning may flag files in this repository. This is expected, because the labs intentionally contain:

- Vulnerable code patterns that resemble malicious behaviour
- Outdated libraries with known CVEs
- Sample credentials and secrets used in exercises

**Recommended approach:**

- Run the labs on a dedicated lab VM rather than adding broad exclusions on your main machine.
- Only add antivirus exclusions on a machine you control and that is isolated from sensitive data.
- Follow your organization's security policy before downloading this repository to a corporate device.

If a security tool flags something that appears to be **genuinely malicious** (rather than an intentional training pattern), please report it.

---

## 📬 Contact

**Security Contact:** Lourdhu Raj Periyanayagam S
📧 **Email:** [lourdhuraj143@gmail.com](mailto:lourdhuraj143@gmail.com)
🐙 **GitHub:** [@loursha](https://github.com/loursha)
🔒 **Private reporting:** [Security tab](https://github.com/loursha/PolyVuln-Web-Application-Labs/security)

---

<div align="center">

**Thank you for helping keep PolyVuln safe to learn with.** 💙

</div>
