import React, { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { api } from "../api/client.js";
import { useSession } from "../App.jsx";

export default function LoginPage() {
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState(null);
  const navigate = useNavigate();
  const session = useSession();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(null);
    try {
      // NOTE: this form intentionally posts over plain HTTP with no
      // additional transport protection, for CWE-319 (cleartext
      // transmission) demonstration. See VULNERABILITY #10 in README.md.
      const sid = new URLSearchParams(window.location.search).get("sid");
      const result = await api.login(username, password, sid);
      session.refreshSession();
      session.refreshCart();
      session.showToast(`Welcome back, ${result.username}!`);
      navigate("/");
    } catch (err) {
      setError(err.message || "Invalid username or password.");
    }
  };

  return (
    <div className="form-card">
      <h2 className="text-center mb-2">Welcome Back</h2>
      {error && <div className="alert alert-danger">{error}</div>}
      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label>Username</label>
          <input type="text" value={username} onChange={(e) => setUsername(e.target.value)} required autoFocus />
        </div>
        <div className="form-group">
          <label>Password</label>
          <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
        </div>
        <button type="submit" className="btn btn-primary btn-block">Log In</button>
      </form>
      <p className="text-center mt-2" style={{ fontSize: ".88rem" }}>
        <Link to="/forgot-password">Forgot your password?</Link>
      </p>
      <p className="text-center" style={{ fontSize: ".88rem" }}>
        New here? <Link to="/register">Create an account</Link>
      </p>
    </div>
  );
}
