import React, { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useSession } from "../App.jsx";
import { api } from "../api/client.js";

export default function Navbar() {
  const session = useSession();
  const navigate = useNavigate();
  const [q, setQ] = useState("");

  const handleSearch = (e) => {
    e.preventDefault();
    navigate(`/search?q=${encodeURIComponent(q)}`);
  };

  const handleLogout = async () => {
    await api.logout();
    session.refreshSession();
    session.refreshCart();
    navigate("/");
  };

  return (
    <nav className="navbar">
      <div className="container">
        <Link to="/" className="brand">
          <span className="brand-mark">S</span> StrideStyle
        </Link>
        <form className="nav-search" onSubmit={handleSearch}>
          <input
            type="text"
            placeholder="Search shoes..."
            value={q}
            onChange={(e) => setQ(e.target.value)}
          />
          <button type="submit">&#8594;</button>
        </form>
        <div className="nav-links">
          <Link to="/shop">Shop</Link>
          {session?.username ? (
            <>
              <Link to="/account">Account</Link>
              {session.isAdmin && <Link to="/admin">Admin</Link>}
              <a href="#" onClick={(e) => { e.preventDefault(); handleLogout(); }}>
                Logout
              </a>
            </>
          ) : (
            <>
              <Link to="/login">Login</Link>
              <Link to="/register">Sign Up</Link>
            </>
          )}
          <Link to="/cart" className="nav-cart">
            Cart {session?.cartCount > 0 && <span className="cart-badge">{session.cartCount}</span>}
          </Link>
        </div>
      </div>
    </nav>
  );
}
