import React, { useEffect, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { api } from "../api/client.js";
import { useSession } from "../App.jsx";

export default function WishlistPage() {
  const [items, setItems] = useState([]);
  const navigate = useNavigate();
  const session = useSession();

  const load = () => {
    api.getWishlist().then(setItems).catch(() => navigate("/login"));
  };

  useEffect(load, []);

  const remove = async (artifactId) => {
    await api.removeFromWishlist(artifactId);
    load();
  };

  return (
    <section className="section">
      <div className="container">
        <span className="eyebrow">Saved for Later</span>
        <h1 className="section-title" style={{ marginBottom: 8 }}>My Wishlist</h1>
        <p className="section-subtitle mb-2">
          {/*
            VULNERABILITY #2 - CWE-352 CSRF. Every item here was added via
            POST /api/wishlist/:artifactId/add, which carries no CSRF
            token and relies solely on the session cookie. A malicious
            third-party page can silently add items to a logged-in
            victim's wishlist -- this heart-icon button looks read-only
            and harmless, which is exactly why it was chosen as the sink.
            See README.md for a working PoC.
          */}
          Items you've saved from across the collection.
        </p>

        {items.length === 0 ? (
          <div className="empty-state">
            <div className="icon">&#9825;</div>
            <p>Your wishlist is empty.</p>
            <Link to="/shop" className="btn btn-primary mt-1">Browse the Collection</Link>
          </div>
        ) : (
          <div className="product-grid">
            {items.map((a) => (
              <div className="product-card" key={a.id}>
                <Link to={`/artifact?id=${a.id}`} className="thumb">
                  <img src={`/images/products/${a.image}`} alt={a.name} />
                </Link>
                <div className="product-card-body">
                  <h3><Link to={`/artifact?id=${a.id}`}>{a.name}</Link></h3>
                  <div className="product-origin">{a.origin}</div>
                  <div className="product-price">${parseFloat(a.price).toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>
                  <button className="btn btn-outline btn-block" onClick={() => remove(a.id)}>
                    Remove from Wishlist
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </section>
  );
}
