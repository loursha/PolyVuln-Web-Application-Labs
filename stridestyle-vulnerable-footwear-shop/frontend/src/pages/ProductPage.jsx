import React, { useEffect, useState } from "react";
import { useSearchParams } from "react-router-dom";
import { api } from "../api/client.js";
import { useSession } from "../App.jsx";

export default function ProductPage() {
  const [params] = useSearchParams();
  const id = params.get("id") || "1";
  const [product, setProduct] = useState(null);
  const [reviews, setReviews] = useState([]);
  const [qty, setQty] = useState(1);
  const [rating, setRating] = useState(5);
  const [comment, setComment] = useState("");
  const [error, setError] = useState(null);
  const session = useSession();

  const load = () => {
    api
      .getProduct(id)
      .then((data) => {
        setProduct(data.product);
        setReviews(data.reviews);
        setError(null);
      })
      .catch((err) => setError(err.message));
  };

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  const addToCart = async () => {
    await api.addToCart(product.id, qty);
    session.refreshCart();
    session.showToast("Added to your cart.");
  };

  const submitReview = async (e) => {
    e.preventDefault();
    if (!session?.username) {
      session.showToast("Please log in to leave a review.", "warning");
      return;
    }
    await api.addReview(product.id, rating, comment);
    setComment("");
    load();
    session.showToast("Thanks for your review!");
  };

  if (error) {
    return (
      <div className="container section">
        <div className="alert alert-danger">{error}</div>
      </div>
    );
  }
  if (!product) return <div className="container section">Loading...</div>;

  const sizes = (product.sizes_available || "").split(",").map((s) => s.trim());

  return (
    <section className="section">
      <div className="container">
        <div className="product-detail">
          <div className="thumb-large">
            <img src={`/images/products/${product.image}`} alt={product.name} />
          </div>
          <div className="product-detail-info">
            {!!product.featured && <span className="badge" style={{ position: "static", display: "inline-block", marginBottom: 12 }}>Featured</span>}
            <h1>{product.name}</h1>
            <div className="product-price">${parseFloat(product.price).toFixed(2)}</div>
            <p>{product.description}</p>

            <div className="product-meta">
              <div><span>Material</span>{product.material}</div>
              <div><span>In Stock</span>{product.stock} pairs</div>
            </div>

            <div>
              <span style={{ fontSize: ".78rem", color: "var(--slate)", textTransform: "uppercase", letterSpacing: ".05em" }}>Available Sizes</span>
              <div className="size-row">
                {sizes.map((s) => (
                  <div className="size-chip" key={s}>{s}</div>
                ))}
              </div>
            </div>

            <div className="qty-form">
              <input
                type="number"
                min="1"
                max="10"
                value={qty}
                onChange={(e) => setQty(parseInt(e.target.value || 1))}
              />
              <button className="btn btn-primary" onClick={addToCart}>Add to Cart</button>
            </div>
          </div>
        </div>

        <h2 className="mt-3">Customer Reviews</h2>
        {reviews.length === 0 ? (
          <p style={{ color: "var(--slate)" }}>No reviews yet -- be the first!</p>
        ) : (
          reviews.map((r) => (
            <div className="review" key={r.id}>
              <div className="review-meta">
                <span className="review-stars">{"★".repeat(r.rating)}{"☆".repeat(5 - r.rating)}</span>
                {" — "}<strong>{r.username}</strong> &middot; {new Date(r.created_at).toLocaleDateString()}
              </div>
              {/*
                Reviews render as plain text (React auto-escapes this),
                so this is NOT an XSS sink in this build. See
                VULNERABILITY #14 in OrderConfirmationPage.jsx (relocated
                to the checkout shipping-address field).
              */}
              <div>{r.comment}</div>
            </div>
          ))
        )}

        <div className="form-card" style={{ margin: "30px 0 0" }}>
          <h3>Leave a Review</h3>
          {session?.username ? (
            <form onSubmit={submitReview}>
              <div className="form-group">
                <label>Rating</label>
                <select value={rating} onChange={(e) => setRating(e.target.value)}>
                  <option value="5">★★★★★ Excellent</option>
                  <option value="4">★★★★☆ Great</option>
                  <option value="3">★★★☆☆ Good</option>
                  <option value="2">★★☆☆☆ Fair</option>
                  <option value="1">★☆☆☆☆ Poor</option>
                </select>
              </div>
              <div className="form-group">
                <label>Your Review</label>
                <textarea
                  value={comment}
                  onChange={(e) => setComment(e.target.value)}
                  placeholder="Tell us what you think..."
                  required
                />
              </div>
              <button type="submit" className="btn btn-primary btn-block">Submit Review</button>
            </form>
          ) : (
            <p>Please <a href="/login">log in</a> to leave a review.</p>
          )}
        </div>
      </div>
    </section>
  );
}
