import React, { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { api } from "../api/client.js";
import { useSession } from "../App.jsx";

export default function HomePage() {
  const [featured, setFeatured] = useState([]);
  const [eras, setEras] = useState([]);
  const session = useSession();

  useEffect(() => {
    api.getFeatured().then(setFeatured).catch(() => {});
    api.getEras().then(setEras).catch(() => {});
  }, []);

  const addToCart = async (id) => {
    await api.addToCart(id, 1);
    session.refreshCart();
    session.showToast("Added to your cart.");
  };

  return (
    <>
      <div className="hero">
        <div className="container">
          <div className="hero-inner">
            <span className="hero-eyebrow">Est. Curated Since 1987</span>
            <h1>Every Piece Has a Story</h1>
            <p>
              Meticulously authenticated antiques and artifacts, each
              accompanied by a documented provenance history.
            </p>
            <Link to="/shop" className="btn btn-brass">Browse the Collection</Link>
          </div>
        </div>
      </div>

      <section className="section">
        <div className="container">
          <span className="eyebrow">Browse by Period</span>
          <div className="section-head">
            <h2 className="section-title">Shop by Era</h2>
          </div>
          <div className="chip-row">
            {eras.map((era) => (
              <Link key={era.id} to={`/shop?era=${era.id}`} className="chip">
                {era.name}
              </Link>
            ))}
          </div>

          <span className="eyebrow">Curator's Selection</span>
          <div className="section-head">
            <h2 className="section-title">Featured Artifacts</h2>
          </div>
          <div className="product-grid">
            {featured.map((a) => (
              <div className="product-card" key={a.id}>
                <Link to={`/artifact?id=${a.id}`} className="thumb">
                  <span className="badge">Featured</span>
                  <img src={`/images/products/${a.image}`} alt={a.name} />
                </Link>
                <div className="product-card-body">
                  <h3><Link to={`/artifact?id=${a.id}`}>{a.name}</Link></h3>
                  <div className="product-origin">{a.origin}</div>
                  <div className="product-price">${parseFloat(a.price).toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>
                  <button className="btn btn-primary btn-block" onClick={() => addToCart(a.id)}>
                    Add to Cart
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="section" style={{ background: "var(--burgundy-dark)", color: "var(--parchment)" }}>
        <div className="container">
          <div className="dashboard-grid" style={{ background: "transparent" }}>
            <div className="stat-card" style={{ background: "transparent" }}>
              <div style={{ fontSize: "1.8rem" }}>&#128209;</div>
              <h3 style={{ color: "var(--parchment)" }}>Documented Provenance</h3>
              <p style={{ color: "#CBB89A", fontSize: ".88rem" }}>Every artifact ships with its ownership history.</p>
            </div>
            <div className="stat-card" style={{ background: "transparent" }}>
              <div style={{ fontSize: "1.8rem" }}>&#128269;</div>
              <h3 style={{ color: "var(--parchment)" }}>Independently Authenticated</h3>
              <p style={{ color: "#CBB89A", fontSize: ".88rem" }}>Verified by recognized guilds and bureaus.</p>
            </div>
            <div className="stat-card" style={{ background: "transparent" }}>
              <div style={{ fontSize: "1.8rem" }}>&#128230;</div>
              <h3 style={{ color: "var(--parchment)" }}>Insured Shipping</h3>
              <p style={{ color: "#CBB89A", fontSize: ".88rem" }}>Every piece is fully insured in transit.</p>
            </div>
            <div className="stat-card" style={{ background: "transparent" }}>
              <div style={{ fontSize: "1.8rem" }}>&#127961;</div>
              <h3 style={{ color: "var(--parchment)" }}>Free Appraisals</h3>
              <p style={{ color: "#CBB89A", fontSize: ".88rem" }}>Considering selling? Get a free quote.</p>
            </div>
          </div>
        </div>
      </section>
    </>
  );
}
