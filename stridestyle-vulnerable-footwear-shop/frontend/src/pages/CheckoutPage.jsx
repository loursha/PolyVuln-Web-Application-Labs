import React, { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { api } from "../api/client.js";
import { useSession } from "../App.jsx";

export default function CheckoutPage() {
  const [items, setItems] = useState([]);
  const [total, setTotal] = useState(0);
  const [address, setAddress] = useState("");
  const navigate = useNavigate();
  const session = useSession();

  useEffect(() => {
    api.getCart().then((data) => {
      setItems(data.items);
      setTotal(data.total);
    });
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    const result = await api.checkout(address);
    session.refreshCart();
    session.showToast("Order placed! Thank you for shopping with StrideStyle.");
    navigate(`/order/${result.orderId}`);
  };

  return (
    <section className="section">
      <div className="container">
        <h1 className="section-title" style={{ marginBottom: 32 }}>Checkout</h1>

        {/*
          VULNERABILITY #17 - CWE-1021 Clickjacking. No X-Frame-Options or
          frame-ancestors CSP directive is ever set by the backend, so
          this checkout page can be embedded in an invisible <iframe> on
          an attacker's site to trick a logged-in victim into submitting a
          real order via a UI-redress attack.
        */}
        <div style={{ display: "grid", gridTemplateColumns: "1.4fr 1fr", gap: 40 }}>
          <div className="form-card" style={{ margin: 0 }}>
            <h3>Shipping Address</h3>
            <form onSubmit={handleSubmit}>
              <div className="form-group">
                <label>Full Shipping Address</label>
                <textarea
                  value={address}
                  onChange={(e) => setAddress(e.target.value)}
                  required
                  placeholder="123 Main St, City, State, ZIP"
                />
              </div>
              <div className="form-group">
                <label>Card Number (demo -- not processed)</label>
                <input type="text" placeholder="4242 4242 4242 4242" />
              </div>
              <button type="submit" className="btn btn-primary btn-block">
                Place Order &mdash; ${total.toFixed(2)}
              </button>
            </form>
          </div>

          <div className="cart-summary" style={{ margin: 0 }}>
            <h3 className="mb-1">Order Summary</h3>
            {items.map((item) => (
              <div className="summary-row" key={item.product.id}>
                <span>{item.qty}x {item.product.name}</span>
                <span>${item.subtotal.toFixed(2)}</span>
              </div>
            ))}
            <div className="summary-row summary-total"><span>Total</span><span>${total.toFixed(2)}</span></div>
          </div>
        </div>
      </div>
    </section>
  );
}
