import React, { useState } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import { api } from "../api/client.js";
import { useSession } from "../App.jsx";

export default function ResetPasswordPage() {
  const [params] = useSearchParams();
  const username = params.get("username") || "";
  const token = params.get("token") || "";
  const [password, setPassword] = useState("");
  const [error, setError] = useState(null);
  const navigate = useNavigate();
  const session = useSession();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(null);
    try {
      await api.resetPassword(username, token, password);
      session.showToast("Password updated. Please log in.");
      navigate("/login");
    } catch (err) {
      setError(err.message || "Invalid or expired reset link.");
    }
  };

  return (
    <div className="form-card">
      <h2 className="text-center mb-2">Set a New Password</h2>
      {error && <div className="alert alert-danger">{error}</div>}
      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label>Username</label>
          <input type="text" value={username} disabled />
        </div>
        <div className="form-group">
          <label>New Password</label>
          <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required autoFocus />
        </div>
        <button type="submit" className="btn btn-primary btn-block">Update Password</button>
      </form>
    </div>
  );
}
