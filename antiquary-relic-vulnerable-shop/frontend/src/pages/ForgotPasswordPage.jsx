import React, { useState } from "react";
import { useNavigate } from "react-router-dom";
import { api } from "../api/client.js";

export default function ForgotPasswordPage() {
  const [username, setUsername] = useState("");
  const [result, setResult] = useState(null);
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    const res = await api.forgotPassword(username);
    setResult(res);
  };

  return (
    <div className="form-card">
      <h2 className="text-center mb-2">Reset Your Password</h2>
      <p style={{ fontSize: ".9rem", color: "var(--stone)", textAlign: "center" }}>
        Enter your username and we'll help you reset your password.
      </p>
      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label>Username</label>
          <input type="text" value={username} onChange={(e) => setUsername(e.target.value)} required autoFocus />
        </div>
        <button type="submit" className="btn btn-primary btn-block">Send Reset Link</button>
      </form>

      {result && (
        <div className="alert alert-info mt-2">
          {result.lab_note ? (
            <>
              {result.message}<br />
              <small>{result.lab_note}</small><br />
              <button
                className="btn btn-outline btn-sm mt-1"
                onClick={() => navigate(result.reset_url)}
              >
                Go to reset link
              </button>
            </>
          ) : (
            result.message
          )}
        </div>
      )}
    </div>
  );
}
