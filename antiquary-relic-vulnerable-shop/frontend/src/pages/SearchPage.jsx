import React, { useEffect, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { api } from "../api/client.js";
import { useSession } from "../App.jsx";

export default function SearchPage() {
  const [params] = useSearchParams();
  const q = params.get("q") || "";
  const [results, setResults] = useState([]);
  const session = useSession();

  useEffect(() => {
    api.search(q).then((data) => setResults(data.results)).catch(() => {});
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
          Search results are plain data -- the query term is not
          reflected back into this page as HTML, so this endpoint is NOT
          an XSS sink in this build. See VULNERABILITY #13 on the
          Appraisal Quote page (a deliberately less-obvious sink).
        */}
        <p className="section-subtitle text-center">Showing results for "{q}"</p>

        {results.length === 0 ? (
          <div className="empty-state">
            <div className="icon">&#128269;</div>
            <p>No artifacts matched your search.</p>
          </div>
        ) : (
          <div className="product-grid">
            {results.map((a) => (
              <div className="product-card" key={a.id}>
                <Link to={`/artifact?id=${a.id}`} className="thumb">
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
