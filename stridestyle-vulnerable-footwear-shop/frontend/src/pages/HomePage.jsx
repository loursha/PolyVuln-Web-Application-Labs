import React, { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { api } from "../api/client.js";
import { useSession } from "../App.jsx";

export default function HomePage() {
  const [featured, setFeatured] = useState([]);
  const [categories, setCategories] = useState([]);
  const session = useSession();

  useEffect(() => {
    api.getFeatured().then(setFeatured).catch(() => {});
    api.getCategories().then(setCategories).catch(() => {});
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
            <span className="hero-eyebrow">New Season Collection</span>
            <h1>Built for Every Stride</h1>
            <p>
              Performance running shoes, everyday sneakers, and boots
              engineered for comfort that lasts all day.
            </p>
            <Link to="/shop" className="btn btn-lime">Shop All Footwear</Link>
          </div>
        </div>
      </div>

      <section className="section">
        <div className="container">
          <span className="eyebrow">Browse</span>
          <div className="section-head">
            <h2 className="section-title">Shop by Category</h2>
          </div>
          <div className="chip-row">
            {categories.map((cat) => (
              <Link key={cat.id} to={`/shop?category=${cat.id}`} className="chip">
                {cat.name}
              </Link>
            ))}
          </div>

          <span className="eyebrow">Editor's Picks</span>
          <div className="section-head">
            <h2 className="section-title">Featured Footwear</h2>
          </div>
          <div className="product-grid">
            {featured.map((p) => (
              <div className="product-card" key={p.id}>
                <Link to={`/product?id=${p.id}`} className="thumb">
                  <span className="badge">Featured</span>
                  <img src={`/images/products/${p.image}`} alt={p.name} />
                </Link>
                <div className="product-card-body">
                  <h3><Link to={`/product?id=${p.id}`}>{p.name}</Link></h3>
                  <div className="product-material">{p.material}</div>
                  <div className="product-price">${parseFloat(p.price).toFixed(2)}</div>
                  <button className="btn btn-primary btn-block" onClick={() => addToCart(p.id)}>
                    Add to Cart
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="section" style={{ background: "var(--graphite)", color: "white" }}>
        <div className="container">
          <div className="dashboard-grid" style={{ background: "transparent" }}>
            <div className="stat-card" style={{ background: "transparent" }}>
              <div style={{ fontSize: "1.8rem" }}>&#9889;</div>
              <h3 style={{ color: "white" }}>Performance Engineered</h3>
              <p style={{ color: "#A6A6B0", fontSize: ".88rem" }}>Carbon plates, responsive foam, breathable uppers.</p>
            </div>
            <div className="stat-card" style={{ background: "transparent" }}>
              <div style={{ fontSize: "1.8rem" }}>&#9989;</div>
              <h3 style={{ color: "white" }}>True-to-Size Fit</h3>
              <p style={{ color: "#A6A6B0", fontSize: ".88rem" }}>Detailed sizing on every product page.</p>
            </div>
            <div className="stat-card" style={{ background: "transparent" }}>
              <div style={{ fontSize: "1.8rem" }}>&#128666;</div>
              <h3 style={{ color: "white" }}>Fast Shipping</h3>
              <p style={{ color: "#A6A6B0", fontSize: ".88rem" }}>Free shipping on every order, no minimum.</p>
            </div>
            <div className="stat-card" style={{ background: "transparent" }}>
              <div style={{ fontSize: "1.8rem" }}>&#8635;</div>
              <h3 style={{ color: "white" }}>Easy Returns</h3>
              <p style={{ color: "#A6A6B0", fontSize: ".88rem" }}>30-day hassle-free returns on unworn pairs.</p>
            </div>
          </div>
        </div>
      </section>
    </>
  );
}
