import React, { useEffect, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { api } from "../api/client.js";
import { useSession } from "../App.jsx";

export default function ShopPage() {
  const [params] = useSearchParams();
  const eraId = params.get("era");
  const [artifacts, setArtifacts] = useState([]);
  const [eras, setEras] = useState([]);
  const session = useSession();

  useEffect(() => {
    api.getArtifacts(eraId).then(setArtifacts).catch(() => {});
    api.getEras().then(setEras).catch(() => {});
  }, [eraId]);

  const addToCart = async (id) => {
    await api.addToCart(id, 1);
    session.refreshCart();
    session.showToast("Added to your cart.");
  };

  return (
    <section className="section">
      <div className="container">
        <span className="eyebrow">Full Collection</span>
        <div className="section-head">
          <h1 className="section-title">The Collection</h1>
        </div>

        <div className="chip-row">
          <Link to="/shop" className={`chip ${!eraId ? "active" : ""}`}>All Eras</Link>
          {eras.map((era) => (
            <Link
              key={era.id}
              to={`/shop?era=${era.id}`}
              className={`chip ${eraId && parseInt(eraId) === era.id ? "active" : ""}`}
            >
              {era.name}
            </Link>
          ))}
        </div>

        {artifacts.length === 0 ? (
          <div className="empty-state">
            <div className="icon">&#127961;</div>
            <p>No artifacts found from this era yet.</p>
          </div>
        ) : (
          <div className="product-grid">
            {artifacts.map((a) => (
              <div className="product-card" key={a.id}>
                <Link to={`/artifact?id=${a.id}`} className="thumb">
                  {!!a.featured && <span className="badge">Featured</span>}
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
        )}
      </div>
    </section>
  );
}
