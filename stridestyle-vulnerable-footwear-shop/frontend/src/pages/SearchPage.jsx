import React, { useEffect, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { api } from "../api/client.js";
import { useSession } from "../App.jsx";

export default function SearchPage() {
  const [params] = useSearchParams();
  const q = params.get("q") || "";
  const [results, setResults] = useState([]);
  const [queryEcho, setQueryEcho] = useState("");
  const session = useSession();

  useEffect(() => {
    api
      .search(q)
      .then((data) => {
        setResults(data.results);
        setQueryEcho(data.query_echo);
      })
      .catch(() => {});
  }, [q]);

  const addToCart = async (id) => {
    await api.addToCart(id, 1);
    session.refreshCart();
    session.showToast("Added to your cart.");
  };

  return (
    <section className="section">
      <div className="container">
        <span className="eyebrow">Search</span>
        <div className="section-head">
          <h1 className="section-title">Search Results</h1>
        </div>
        {/*
          Search results are plain data -- query_echo is rendered as
          ordinary React text (auto-escaped), not HTML. This endpoint is
          NOT an XSS sink in this build; see VULNERABILITY #13 in
          Footer.jsx (relocated to the newsletter subscribe confirmation).
        */}
        <p className="section-subtitle">
          Showing results for: "{queryEcho}"
        </p>

        {results.length === 0 ? (
          <div className="empty-state">
            <div className="icon">&#128269;</div>
            <p>No shoes matched your search.</p>
          </div>
        ) : (
          <div className="product-grid">
            {results.map((p) => (
              <div className="product-card" key={p.id}>
                <Link to={`/product?id=${p.id}`} className="thumb">
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
        )}
      </div>
    </section>
  );
}
