import React, { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { api } from "../api/client.js";
import { useSession } from "../App.jsx";

export default function AccountPage() {
  const [user, setUser] = useState(null);
  const [orders, setOrders] = useState([]);
  const [email, setEmail] = useState("");
  const [avatarFile, setAvatarFile] = useState(null);
  const [csrfToken, setCsrfToken] = useState(null);
  const navigate = useNavigate();
  const session = useSession();

  const load = () => {
    api
      .getAccount()
      .then((data) => {
        setUser(data.user);
        setOrders(data.orders);
        setEmail(data.user.email);
      })
      .catch(() => navigate("/login"));
    api.getCsrfToken().then((r) => setCsrfToken(r.csrf_token));
  };

  useEffect(load, []);

  // This request carries a real per-session CSRF token, so it is NOT a
  // CSRF sink in this build. See VULNERABILITY #2 on the Wishlist page
  // (the "add to wishlist" heart icon), a convenience action most
  // testers assume is harmless and don't think to check for CSRF.
  const handleUpdateEmail = async (e) => {
    e.preventDefault();
    await api.updateEmail(email, csrfToken);
    session.showToast("Email address updated.");
    load();
  };

  const handleAvatarUpload = async (e) => {
    e.preventDefault();
    if (!avatarFile) return;
    await api.uploadAvatar(avatarFile);
    session.showToast("Avatar updated!");
    load();
  };

  if (!user) return <div className="container section">Loading...</div>;

  return (
    <section className="section">
      <div className="container">
        <span className="eyebrow">Dashboard</span>
        <h1 className="section-title" style={{ marginBottom: 32 }}>My Account</h1>

        <div style={{ display: "grid", gridTemplateColumns: "1fr 1.4fr", gap: 30, alignItems: "start" }}>
          <div className="form-card" style={{ margin: 0 }}>
            <div className="text-center mb-2">
              <img
                src={user.avatar === "default-avatar.jpg" ? "/images/default-avatar.jpg" : `/uploads/${user.avatar}`}
                alt="avatar"
                className="avatar-sm"
                style={{ width: 90, height: 90 }}
              />
            </div>
            <h3>Profile</h3>
            <p><strong>Username:</strong> {user.username}</p>
            <p><strong>Member since:</strong> {new Date(user.created_at).toLocaleDateString()}</p>

            <form onSubmit={handleUpdateEmail} className="mt-2">
              <div className="form-group">
                <label>Email Address</label>
                <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
              </div>
              <button type="submit" className="btn btn-primary btn-block">Update Email</button>
            </form>

            <form onSubmit={handleAvatarUpload} className="mt-2">
              <div className="form-group">
                <label>Change Avatar</label>
                <input type="file" accept="image/*" onChange={(e) => setAvatarFile(e.target.files[0])} />
              </div>
              <button type="submit" className="btn btn-outline btn-block">Upload</button>
            </form>
          </div>

          <div>
            <h3>Order History</h3>
            {orders.length > 0 ? (
              <table className="data-table">
                <thead><tr><th>Order #</th><th>Date</th><th>Status</th><th>Total</th></tr></thead>
                <tbody>
                  {orders.map((o) => (
                    <tr key={o.id}>
                      <td>#{o.id}</td>
                      <td>{new Date(o.created_at).toLocaleDateString()}</td>
                      <td>{o.status}</td>
                      <td>${parseFloat(o.total).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            ) : (
              <div className="empty-state">
                <div className="icon">&#128230;</div>
                <p>No orders yet. <a href="/shop">Start browsing</a></p>
              </div>
            )}
          </div>
        </div>
      </div>
    </section>
  );
}
