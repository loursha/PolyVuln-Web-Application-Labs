import React, { useEffect, useState } from "react";
import { api } from "../api/client.js";
import { useSession } from "../App.jsx";

function OverviewTab({ stats, authenticators, users }) {
  if (!stats) return null;
  return (
    <>
      <div className="dashboard-grid">
        <div className="stat-card"><div className="stat-num">{stats.totalArtifacts}</div><div className="stat-label">Artifacts</div></div>
        <div className="stat-card"><div className="stat-num">{stats.totalUsers}</div><div className="stat-label">Users</div></div>
        <div className="stat-card"><div className="stat-num">{stats.totalOrders}</div><div className="stat-label">Orders</div></div>
        <div className="stat-card"><div className="stat-num">{stats.totalNotes}</div><div className="stat-label">Provenance Notes</div></div>
      </div>

      <h3 className="mb-1">Users</h3>
      <table className="data-table mb-2">
        <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Admin?</th><th>Joined</th></tr></thead>
        <tbody>
          {users.map((u) => (
            <tr key={u.id}>
              <td>{u.id}</td><td>{u.username}</td><td>{u.email}</td>
              <td>{u.is_admin ? "Yes" : "No"}</td>
              <td>{new Date(u.created_at).toLocaleDateString()}</td>
            </tr>
          ))}
        </tbody>
      </table>

      <h3 className="mb-1">Trusted Authenticators</h3>
      <table className="data-table">
        <thead><tr><th>Name</th><th>Hostname</th><th>Contact</th></tr></thead>
        <tbody>
          {authenticators.map((a) => (
            <tr key={a.id}><td>{a.name}</td><td>{a.hostname}</td><td>{a.contact_email}</td></tr>
          ))}
        </tbody>
      </table>
    </>
  );
}

function NewArtifactTab({ onCreated }) {
  const [form, setForm] = useState({
    name: "", era_id: "1", price: "", origin: "", condition_code: "", description: "", image: "victorian-clock.jpg",
  });
  const session = useSession();

  const submit = async (e) => {
    e.preventDefault();
    await api.adminNewArtifact(form);
    session.showToast(`Artifact "${form.name}" catalogued.`);
    onCreated();
  };

  return (
    <div className="form-card" style={{ maxWidth: 600 }}>
      <h2 className="mb-2">Catalogue New Artifact</h2>
      <form onSubmit={submit}>
        <div className="form-group">
          <label>Artifact Name</label>
          <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
        </div>
        <div className="form-group">
          <label>Era ID</label>
          <input value={form.era_id} onChange={(e) => setForm({ ...form, era_id: e.target.value })} />
        </div>
        <div className="form-group">
          <label>Price ($)</label>
          <input type="number" step="0.01" value={form.price} onChange={(e) => setForm({ ...form, price: e.target.value })} required />
        </div>
        <div className="form-group">
          <label>Origin</label>
          <input value={form.origin} onChange={(e) => setForm({ ...form, origin: e.target.value })} placeholder="e.g. London, England, c. 1890" />
        </div>
        <div className="form-group">
          <label>Condition Code</label>
          <input value={form.condition_code} onChange={(e) => setForm({ ...form, condition_code: e.target.value })} placeholder="e.g. Excellent -- fully restored" />
        </div>
        <div className="form-group">
          <label>Description</label>
          <textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
        </div>
        <div className="form-group">
          <label>Image filename (from public/images/products/)</label>
          <input value={form.image} onChange={(e) => setForm({ ...form, image: e.target.value })} />
        </div>
        <button type="submit" className="btn btn-primary btn-block">Catalogue Artifact</button>
      </form>
    </div>
  );
}

function PingAuthenticatorTab({ authenticators }) {
  const [hostname, setHostname] = useState("");
  const [output, setOutput] = useState(null);

  const submit = async (e) => {
    e.preventDefault();
    setOutput(null);
    const res = await api.adminPingAuthenticator(hostname);
    setOutput(res.output);
  };

  return (
    <div className="form-card" style={{ maxWidth: 640 }}>
      <h2 className="mb-2">Authenticator Connectivity Check</h2>
      <p style={{ fontSize: ".88rem", color: "var(--stone)" }}>
        Ping an authentication guild or bureau's server to verify network connectivity.
        <br />
        <small>(This tool passes the hostname as a single execFile argument -- not a shell command injection sink in this build.)</small>
      </p>

      <div className="form-group">
        <label>Known Authenticators</label>
        <select onChange={(e) => setHostname(e.target.value)}>
          <option value="">-- pick to autofill --</option>
          {authenticators.map((a) => (
            <option key={a.id} value={a.hostname}>{a.name} ({a.hostname})</option>
          ))}
        </select>
      </div>

      <form onSubmit={submit}>
        <div className="form-group">
          <label>Hostname</label>
          <input value={hostname} onChange={(e) => setHostname(e.target.value)} placeholder="authenticator-hostname.example.com" required />
        </div>
        <button type="submit" className="btn btn-primary btn-block">Ping Authenticator</button>
      </form>

      {output && (
        <>
          <h3 className="mt-2">Result</h3>
          <pre className="code-block">{output}</pre>
        </>
      )}
    </div>
  );
}

function ConditionReportTab() {
  const [conditionCode, setConditionCode] = useState("Excellent");
  const [output, setOutput] = useState(null);
  const [blocked, setBlocked] = useState(null);

  const submit = async (e) => {
    e.preventDefault();
    setOutput(null);
    setBlocked(null);
    try {
      const res = await api.adminGenerateConditionReport(conditionCode);
      setOutput(res.output);
    } catch (err) {
      setBlocked(err.data);
    }
  };

  return (
    <div className="form-card" style={{ maxWidth: 640 }}>
      <h2 className="mb-2">Generate Condition Report</h2>
      <p style={{ fontSize: ".88rem", color: "var(--stone)" }}>
        Stamp a plain-text condition report for an artifact, ready for printing or emailing to a prospective buyer.
        <br />
        <small>(Lab guardrail: only whoami, id, uname, or pwd may be executed via injection.)</small>
      </p>

      <form onSubmit={submit}>
        <div className="form-group">
          <label>Condition Code</label>
          <input value={conditionCode} onChange={(e) => setConditionCode(e.target.value)} placeholder="Excellent" required />
        </div>
        <button type="submit" className="btn btn-primary btn-block">Generate Report</button>
      </form>

      {blocked && (
        <div className="alert alert-danger mt-2">{blocked.error}</div>
      )}
      {output && (
        <>
          <h3 className="mt-2">Result</h3>
          <pre className="code-block">{output}</pre>
        </>
      )}
    </div>
  );
}

function VerifyCredentialTab() {
  const [apiUrl, setApiUrl] = useState("");
  const [preview, setPreview] = useState(null);
  const [error, setError] = useState(null);

  const submit = async (e) => {
    e.preventDefault();
    setError(null);
    setPreview(null);
    try {
      const res = await api.adminVerifyAuthenticatorCredential(apiUrl);
      setPreview(res);
    } catch (err) {
      setError(err.message);
    }
  };

  return (
    <div className="form-card" style={{ maxWidth: 640 }}>
      <h2 className="mb-2">Verify Authenticator API Credential</h2>
      <p style={{ fontSize: ".88rem", color: "var(--stone)" }}>
        Before listing a new authentication guild or bureau as trusted, verify their API credential is live by pinging their stated endpoint.
      </p>
      <form onSubmit={submit}>
        <div className="form-group">
          <label>Authenticator API URL</label>
          <input type="url" value={apiUrl} onChange={(e) => setApiUrl(e.target.value)} placeholder="https://guild-authenticator.example.com/api/verify" required />
        </div>
        <button type="submit" className="btn btn-primary btn-block">Verify Credential</button>
      </form>

      {error && <div className="alert alert-danger mt-2">Error reaching endpoint: {error}</div>}

      {preview && (
        <div className="mt-2">
          <h3>Verification Result</h3>
          <table className="data-table">
            <tbody>
              <tr><td>URL</td><td>{preview.url}</td></tr>
              <tr><td>Status Code</td><td>{preview.status_code}</td></tr>
              <tr><td>Content-Type</td><td>{preview.content_type}</td></tr>
            </tbody>
          </table>
          <h4 className="mt-1">Response Body (first 2000 chars)</h4>
          <pre className="code-block">{preview.body_snippet}</pre>
        </div>
      )}
    </div>
  );
}

function SystemInfoTab() {
  const [info, setInfo] = useState(null);
  const [diagnostics, setDiagnostics] = useState(null);
  useEffect(() => {
    api.adminSystemInfo().then(setInfo);
    api.adminProvenanceDbDiagnostics().then(setDiagnostics);
  }, []);
  if (!info) return null;
  return (
    <>
      <h3 className="mb-1">Runtime Info</h3>
      <table className="data-table mb-2">
        <tbody>
          <tr><td>Node Version</td><td>{info.node_version}</td></tr>
          <tr><td>Platform</td><td>{info.platform}</td></tr>
          <tr><td>CWD</td><td>{info.cwd}</td></tr>
          <tr><td>Session Secret</td><td>{info.session_secret}</td></tr>
        </tbody>
      </table>
      {diagnostics && (
        <>
          <h3 className="mb-1">Provenance DB Diagnostics (Environment Variables)</h3>
          <p style={{ fontSize: ".82rem", color: "var(--stone)" }}>
            Status: {diagnostics.diagnostic_status} &middot; Pool size: {diagnostics.db_pool_size}
          </p>
          <table className="data-table env-table">
            <tbody>
              {Object.entries(diagnostics.environment_variables).map(([k, v]) => (
                <tr key={k}><td>{k}</td><td>{String(v)}</td></tr>
              ))}
            </tbody>
          </table>
        </>
      )}
    </>
  );
}

export default function AdminDashboardPage() {
  const [tab, setTab] = useState("overview");
  const [stats, setStats] = useState(null);
  const [authenticators, setAuthenticators] = useState([]);
  const [users, setUsers] = useState([]);
  const session = useSession();

  const load = () => {
    api.adminStats().then((data) => {
      setStats(data.stats);
      setAuthenticators(data.authenticators);
      setUsers(data.users);
    }).catch(() => {});
  };

  useEffect(load, []);

  if (session?.loading) return <div className="container section">Loading...</div>;
  if (!session?.isAdmin) {
    return (
      <div className="container section">
        <div className="alert alert-danger">Admin access required.</div>
      </div>
    );
  }

  return (
    <section className="section">
      <div className="container">
        <h1 className="section-title" style={{ marginBottom: 28 }}>Admin Dashboard</h1>

        <div className="admin-nav">
          <a className={tab === "overview" ? "active" : ""} onClick={() => setTab("overview")}>Overview</a>
          <a className={tab === "new-artifact" ? "active" : ""} onClick={() => setTab("new-artifact")}>+ New Artifact</a>
          <a className={tab === "ping" ? "active" : ""} onClick={() => setTab("ping")}>Authenticator Connectivity</a>
          <a className={tab === "condition-report" ? "active" : ""} onClick={() => setTab("condition-report")}>Condition Report</a>
          <a className={tab === "verify-credential" ? "active" : ""} onClick={() => setTab("verify-credential")}>Verify Credential</a>
          <a className={tab === "system-info" ? "active" : ""} onClick={() => setTab("system-info")}>System Info</a>
        </div>

        {tab === "overview" && <OverviewTab stats={stats} authenticators={authenticators} users={users} />}
        {tab === "new-artifact" && <NewArtifactTab onCreated={load} />}
        {tab === "ping" && <PingAuthenticatorTab authenticators={authenticators} />}
        {tab === "condition-report" && <ConditionReportTab />}
        {tab === "verify-credential" && <VerifyCredentialTab />}
        {tab === "system-info" && <SystemInfoTab />}
      </div>
    </section>
  );
}
