import React, { useEffect, useState } from "react";
import { api } from "../api/client.js";
import { useSession } from "../App.jsx";

function OverviewTab({ stats, suppliers, users }) {
  if (!stats) return null;
  return (
    <>
      <div className="dashboard-grid">
        <div className="stat-card"><div className="stat-num">{stats.totalProducts}</div><div className="stat-label">Products</div></div>
        <div className="stat-card"><div className="stat-num">{stats.totalUsers}</div><div className="stat-label">Users</div></div>
        <div className="stat-card"><div className="stat-num">{stats.totalOrders}</div><div className="stat-label">Orders</div></div>
        <div className="stat-card"><div className="stat-num">{stats.totalReviews}</div><div className="stat-label">Reviews</div></div>
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

      <h3 className="mb-1">Suppliers</h3>
      <table className="data-table">
        <thead><tr><th>Name</th><th>Hostname</th><th>Contact</th></tr></thead>
        <tbody>
          {suppliers.map((s) => (
            <tr key={s.id}><td>{s.name}</td><td>{s.hostname}</td><td>{s.contact_email}</td></tr>
          ))}
        </tbody>
      </table>
    </>
  );
}

function NewProductTab({ onCreated }) {
  const [form, setForm] = useState({
    name: "", category_id: "1", price: "", sizes_available: "", material: "", description: "", image: "running-shoe-1.jpg",
  });
  const session = useSession();

  const submit = async (e) => {
    e.preventDefault();
    await api.adminNewProduct(form);
    session.showToast(`Product "${form.name}" created.`);
    onCreated();
  };

  return (
    <div className="form-card" style={{ maxWidth: 600 }}>
      <h2 className="mb-2">Add New Product</h2>
      <form onSubmit={submit}>
        <div className="form-group">
          <label>Product Name</label>
          <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
        </div>
        <div className="form-group">
          <label>Category ID</label>
          <input value={form.category_id} onChange={(e) => setForm({ ...form, category_id: e.target.value })} />
        </div>
        <div className="form-group">
          <label>Price ($)</label>
          <input type="number" step="0.01" value={form.price} onChange={(e) => setForm({ ...form, price: e.target.value })} required />
        </div>
        <div className="form-group">
          <label>Sizes Available</label>
          <input value={form.sizes_available} onChange={(e) => setForm({ ...form, sizes_available: e.target.value })} placeholder="e.g. 7,8,9,10,11" />
        </div>
        <div className="form-group">
          <label>Material</label>
          <input value={form.material} onChange={(e) => setForm({ ...form, material: e.target.value })} />
        </div>
        <div className="form-group">
          <label>Description</label>
          <textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
        </div>
        <div className="form-group">
          <label>Image filename (from public/images/products/)</label>
          <input value={form.image} onChange={(e) => setForm({ ...form, image: e.target.value })} />
        </div>
        <button type="submit" className="btn btn-primary btn-block">Create Product</button>
      </form>
    </div>
  );
}

function PingSupplierTab({ suppliers }) {
  const [hostname, setHostname] = useState("");
  const [output, setOutput] = useState(null);

  const submit = async (e) => {
    e.preventDefault();
    setOutput(null);
    const res = await api.adminPingSupplier(hostname);
    setOutput(res.output);
  };

  return (
    <div className="form-card" style={{ maxWidth: 640 }}>
      <h2 className="mb-2">Supplier Connectivity Check</h2>
      <p style={{ fontSize: ".88rem", color: "var(--slate)" }}>
        Ping a supplier's server to verify network connectivity before placing a wholesale order.
        <br />
        <small>(This tool passes the hostname as a single execFile argument -- not a shell command injection sink in this build.)</small>
      </p>

      <div className="form-group">
        <label>Known Suppliers</label>
        <select onChange={(e) => setHostname(e.target.value)}>
          <option value="">-- pick to autofill --</option>
          {suppliers.map((s) => (
            <option key={s.id} value={s.hostname}>{s.name} ({s.hostname})</option>
          ))}
        </select>
      </div>

      <form onSubmit={submit}>
        <div className="form-group">
          <label>Hostname</label>
          <input value={hostname} onChange={(e) => setHostname(e.target.value)} placeholder="supplier-hostname.example.com" required />
        </div>
        <button type="submit" className="btn btn-primary btn-block">Ping Supplier</button>
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

function ExportSizeChartTab() {
  const [regionCode, setRegionCode] = useState("US");
  const [output, setOutput] = useState(null);
  const [blocked, setBlocked] = useState(null);

  const submit = async (e) => {
    e.preventDefault();
    setOutput(null);
    setBlocked(null);
    try {
      const res = await api.adminExportSizeChart(regionCode);
      setOutput(res.output);
    } catch (err) {
      setBlocked(err.data);
    }
  };

  return (
    <div className="form-card" style={{ maxWidth: 640 }}>
      <h2 className="mb-2">Export Size Conversion Chart</h2>
      <p style={{ fontSize: ".88rem", color: "var(--slate)" }}>
        Generate a plain-text size-conversion chart stamped with a region code (e.g. US, EU, UK) for print or email.
        <br />
        <small>(Lab guardrail: only whoami, id, uname, or pwd may be executed via injection.)</small>
      </p>

      <form onSubmit={submit}>
        <div className="form-group">
          <label>Region Code</label>
          <input value={regionCode} onChange={(e) => setRegionCode(e.target.value)} placeholder="US" required />
        </div>
        <button type="submit" className="btn btn-primary btn-block">Export Chart</button>
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

function VerifyWebhookTab() {
  const [webhookUrl, setWebhookUrl] = useState("");
  const [preview, setPreview] = useState(null);
  const [error, setError] = useState(null);

  const submit = async (e) => {
    e.preventDefault();
    setError(null);
    setPreview(null);
    try {
      const res = await api.adminVerifyUnsubscribeWebhook(webhookUrl);
      setPreview(res);
    } catch (err) {
      setError(err.message);
    }
  };

  return (
    <div className="form-card" style={{ maxWidth: 640 }}>
      <h2 className="mb-2">Verify Unsubscribe Webhook</h2>
      <p style={{ fontSize: ".88rem", color: "var(--slate)" }}>
        Register and test-ping a webhook URL that fires when a customer unsubscribes, so your integration is confirmed reachable before it goes live.
      </p>
      <form onSubmit={submit}>
        <div className="form-group">
          <label>Webhook URL</label>
          <input type="url" value={webhookUrl} onChange={(e) => setWebhookUrl(e.target.value)} placeholder="https://your-integration.example.com/webhook" required />
        </div>
        <button type="submit" className="btn btn-primary btn-block">Verify Webhook</button>
      </form>

      {error && <div className="alert alert-danger mt-2">Error reaching webhook: {error}</div>}

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
  useEffect(() => { api.adminSystemInfo().then(setInfo); }, []);
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
      <h3 className="mb-1">Environment Variables</h3>
      <table className="data-table env-table">
        <tbody>
          {Object.entries(info.env).map(([k, v]) => (
            <tr key={k}><td>{k}</td><td>{String(v)}</td></tr>
          ))}
        </tbody>
      </table>
    </>
  );
}

export default function AdminDashboardPage() {
  const [tab, setTab] = useState("overview");
  const [stats, setStats] = useState(null);
  const [suppliers, setSuppliers] = useState([]);
  const [users, setUsers] = useState([]);
  const session = useSession();

  const load = () => {
    api.adminStats().then((data) => {
      setStats(data.stats);
      setSuppliers(data.suppliers);
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
          <a className={tab === "new-product" ? "active" : ""} onClick={() => setTab("new-product")}>+ New Product</a>
          <a className={tab === "ping" ? "active" : ""} onClick={() => setTab("ping")}>Supplier Connectivity</a>
          <a className={tab === "size-chart" ? "active" : ""} onClick={() => setTab("size-chart")}>Export Size Chart</a>
          <a className={tab === "verify-webhook" ? "active" : ""} onClick={() => setTab("verify-webhook")}>Verify Webhook</a>
          <a className={tab === "system-info" ? "active" : ""} onClick={() => setTab("system-info")}>System Info</a>
        </div>

        {tab === "overview" && <OverviewTab stats={stats} suppliers={suppliers} users={users} />}
        {tab === "new-product" && <NewProductTab onCreated={load} />}
        {tab === "ping" && <PingSupplierTab suppliers={suppliers} />}
        {tab === "size-chart" && <ExportSizeChartTab />}
        {tab === "verify-webhook" && <VerifyWebhookTab />}
        {tab === "system-info" && <SystemInfoTab />}
      </div>
    </section>
  );
}
