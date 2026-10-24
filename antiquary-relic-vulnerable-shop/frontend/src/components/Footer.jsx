import React, { useState } from "react";
import { Link } from "react-router-dom";
import { api } from "../api/client.js";
import { useSession } from "../App.jsx";

export default function Footer() {
  const [email, setEmail] = useState("");
  const session = useSession();

  const handleSubscribe = async (e) => {
    e.preventDefault();
    await api.submitAppraisalRequest(email, "(newsletter signup, no item description)");
    session.showToast("You're on our mailing list for new arrivals and auction previews.", "success");
    setEmail("");
  };

  return (
    <footer>
      <div className="container">
        <div className="footer-grid">
          <div>
            <h4>The Antiquary &amp; Relic Co.</h4>
            <p style={{ fontSize: ".88rem", color: "#A98F73", maxWidth: 280 }}>
              Carefully authenticated antiques and artifacts, each with a
              documented provenance history.
            </p>
          </div>
          <div>
            <h4>Collection</h4>
            <Link to="/shop">Browse All</Link>
            <Link to="/shop?era=1">Victorian</Link>
            <Link to="/shop?era=2">Art Deco</Link>
            <Link to="/shop?era=5">Newly Catalogued</Link>
          </div>
          <div>
            <h4>Services</h4>
            <Link to="/appraisal-quote">Free Appraisal Quote</Link>
            <Link to="/account">My Account</Link>
            <Link to="/forgot-password">Forgot Password</Link>
          </div>
          <div>
            <h4>Stay Informed</h4>
            <form className="newsletter-box" onSubmit={handleSubscribe}>
              <input
                type="email"
                placeholder="you@example.com"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
              />
              <button type="submit" className="btn btn-brass btn-sm">Join</button>
            </form>
          </div>
        </div>
        <div className="footer-bottom">
          &copy; 2026 The Antiquary &amp; Relic Co. &mdash; Training lab application. Not a real store.
        </div>
      </div>
    </footer>
  );
}
