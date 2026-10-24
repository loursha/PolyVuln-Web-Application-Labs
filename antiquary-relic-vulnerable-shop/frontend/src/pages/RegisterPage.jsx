import React, { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { api } from "../api/client.js";
import { useSession } from "../App.jsx";

export default function RegisterPage() {
  const [username, setUsername] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState(null);
  const navigate = useNavigate();
  const session = useSession();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(null);
    try {
      // NOTE: the request body always sends is_admin: false from this
      // form, but the backend trusts whatever value the CLIENT sends in
      // that field (VULNERABILITY #16, CWE-266) -- a raw POST to
      // /api/register with { is_admin: true } in the JSON body (e.g. via
      // curl or devtools) bypasses this UI entirely and self-registers
      // an admin.
      await api.register(username, email, password, false);
      session.showToast("Account created! Please log in.");
      navigate("/login");
    } catch (err) {
      setError(err.message || "Registration failed.");
    }
  };

  return (
    <div className="form-card">
      <h2 className="text-center mb-2">Create Your Account</h2>
      {error && <div className="alert alert-danger">{error}</div>}
      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label>Username</label>
          <input type="text" value={username} onChange={(e) => setUsername(e.target.value)} required autoFocus />
        </div>
        <div className="form-group">
          <label>Email</label>
          <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
        </div>
        <div className="form-group">
          <label>Password</label>
          <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
        </div>
        <button type="submit" className="btn btn-primary btn-block">Sign Up</button>
      </form>
      <p className="text-center mt-2" style={{ fontSize: ".88rem" }}>
        Already have an account? <Link to="/login">Log in</Link>
      </p>
    </div>
  );
}
