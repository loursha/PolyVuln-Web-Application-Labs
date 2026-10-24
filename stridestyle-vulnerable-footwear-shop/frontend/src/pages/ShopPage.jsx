import React, { useEffect, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { api } from "../api/client.js";
import { useSession } from "../App.jsx";

export default function ShopPage() {
  const [params] = useSearchParams();
  const categoryId = params.get("category");
  const [products, setProducts] = useState([]);
  const [categories, setCategories] = useState([]);
  const session = useSession();

  useEffect(() => {
    api.getProducts(categoryId).then(setProducts).catch(() => {});
    api.getCategories().then(setCategories).catch(() => {});
  }, [categoryId]);

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
          <h1 className="section-title">Shop All Footwear</h1>
        </div>

        <div className="chip-row">
          <Link to="/shop" className={`chip ${!categoryId ? "active" : ""}`}>All</Link>
          {categories.map((cat) => (
            <Link
              key={cat.id}
              to={`/shop?category=${cat.id}`}
              className={`chip ${categoryId && parseInt(categoryId) === cat.id ? "active" : ""}`}
            >
              {cat.name}
            </Link>
          ))}
        </div>

        {products.length === 0 ? (
          <div className="empty-state">
            <div className="icon">&#128094;</div>
            <p>No shoes found in this category yet.</p>
          </div>
        ) : (
          <div className="product-grid">
            {products.map((p) => (
              <div className="product-card" key={p.id}>
                <Link to={`/product?id=${p.id}`} className="thumb">
                  {!!p.featured && <span className="badge">Featured</span>}
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
