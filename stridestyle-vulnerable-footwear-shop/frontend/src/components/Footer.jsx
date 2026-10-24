import React, { useState } from "react";
import { Link } from "react-router-dom";
import { api } from "../api/client.js";
import { useSession } from "../App.jsx";

export default function Footer() {
  const [email, setEmail] = useState("");
  const [confirmation, setConfirmation] = useState(null);
  const session = useSession();

  const handleSubscribe = async (e) => {
    e.preventDefault();
    const result = await api.subscribeNewsletter(email);
    // VULNERABILITY #13 - CWE-80 Reflected XSS. The backend runs the
    // submitted email through a deliberately-partial filter (blocks
    // <script> tags and the literal alert( call only) and echoes it back
    // in confirmation_message; rendering that field with
    // dangerouslySetInnerHTML means any payload that survives the filter
    // (e.g. <img src=x onerror=...>) executes immediately on submit, with
    // no click-through link required.
    setConfirmation(result.confirmation_message);
    session.showToast("Subscribed! Check the confirmation below.", "success");
    setEmail("");
  };

  return (
    <footer>
      <div className="container">
        <div className="footer-grid">
          <div>
            <h4>StrideStyle</h4>
            <p style={{ fontSize: ".88rem", color: "#9E9EA8", maxWidth: 280 }}>
              Footwear built for every step -- from marathon mornings to
              boardroom afternoons.
            </p>
          </div>
          <div>
            <h4>Shop</h4>
            <Link to="/shop">All Shoes</Link>
            <Link to="/shop?category=1">Running</Link>
            <Link to="/shop?category=2">Sneakers</Link>
            <Link to="/shop?category=5">New Arrivals</Link>
          </div>
          <div>
            <h4>Support</h4>
            <Link to="/account">My Account</Link>
            <Link to="/forgot-password">Forgot Password</Link>
            <Link to="/cart">Cart</Link>
          </div>
          <div>
            <h4>Stay in the loop</h4>
            <form className="newsletter-box" onSubmit={handleSubscribe}>
              <input
                type="email"
                placeholder="you@example.com"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
              />
              <button type="submit" className="btn btn-lime btn-sm">Join</button>
            </form>
            {confirmation && (
              <p
                style={{ fontSize: ".82rem", color: "var(--lime)", marginTop: 10 }}
                dangerouslySetInnerHTML={{ __html: confirmation }}
              />
            )}
          </div>
        </div>
        <div className="footer-bottom">
          &copy; 2026 StrideStyle Footwear Co. &mdash; Training lab application. Not a real store.
        </div>
      </div>
    </footer>
  );
}
