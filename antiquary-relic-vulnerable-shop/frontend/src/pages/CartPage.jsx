import React, { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { api } from "../api/client.js";
import { useSession } from "../App.jsx";

export default function CartPage() {
  const [items, setItems] = useState([]);
  const [total, setTotal] = useState(0);
  const [preferredPace, setPreferredPace] = useState("Leisurely");
  const session = useSession();

  const load = () => {
    api.getCart().then((data) => {
      setItems(data.items);
      setTotal(data.total);
    });
  };

  useEffect(load, []);

  const removeItem = async (id) => {
    await api.removeFromCart(id);
    session.refreshCart();
    load();
  };

  const savePreferences = async () => {
    await api.saveShowroomPreferences({ pace: preferredPace, savedAt: new Date().toISOString() });
    session.showToast("Your showroom visit preferences have been saved for next time.", "info");
  };

  const restorePreferences = async () => {
    try {
      const res = await api.restoreShowroomPreferences();
      if (res.success && res.preferences) {
        setPreferredPace(res.preferences.pace || "Leisurely");
        session.showToast("Your saved visit preferences have been restored.", "success");
      } else {
        session.showToast("No saved preferences found.", "info");
      }
    } catch (err) {
      session.showToast("Could not restore saved preferences.", "danger");
    }
  };

  return (
    <section className="section">
      <div className="container">
        <h1 className="section-title" style={{ marginBottom: 32 }}>Your Cart</h1>

        {items.length > 0 ? (
          <>
            <table className="cart-table">
              <thead>
                <tr><th></th><th>Artifact</th><th>Price</th><th>Qty</th><th>Subtotal</th><th></th></tr>
              </thead>
              <tbody>
                {items.map((item) => (
                  <tr key={item.artifact.id}>
                    <td><img className="cart-item-thumb" src={`/images/products/${item.artifact.image}`} alt="" /></td>
                    <td>{item.artifact.name}</td>
                    <td>${parseFloat(item.artifact.price).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                    <td>{item.qty}</td>
                    <td>${item.subtotal.toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                    <td>
                      <button className="btn btn-sm btn-outline" onClick={() => removeItem(item.artifact.id)}>
                        Remove
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>

            <div className="form-card" style={{ margin: "10px 0 30px", maxWidth: "none" }}>
              <h3 className="mb-1">Plan Your Next Showroom Visit</h3>
              <p style={{ fontSize: ".85rem", color: "var(--stone)" }}>
                Save your preferred browsing pace so our staff can tailor your next in-person viewing.
              </p>
              <div className="form-group">
                <label>Preferred Pace</label>
                <select value={preferredPace} onChange={(e) => setPreferredPace(e.target.value)}>
                  <option>Leisurely</option>
                  <option>Focused</option>
                  <option>By Appointment Only</option>
                </select>
              </div>
              <div style={{ display: "flex", gap: 10 }}>
                <button className="btn btn-outline btn-sm" onClick={savePreferences}>Save Preferences</button>
                <button className="btn btn-outline btn-sm" onClick={restorePreferences}>Restore Saved Preferences</button>
              </div>
            </div>

            <div className="cart-summary">
              <div className="summary-row"><span>Subtotal</span><span>${total.toLocaleString(undefined, { minimumFractionDigits: 2 })}</span></div>
              <div className="summary-row"><span>Shipping</span><span>Insured, quoted at checkout</span></div>
              <div className="summary-row summary-total"><span>Total</span><span>${total.toLocaleString(undefined, { minimumFractionDigits: 2 })}</span></div>
              <Link to="/checkout" className="btn btn-primary btn-block mt-1">Proceed to Checkout</Link>
            </div>
          </>
        ) : (
          <div className="empty-state">
            <div className="icon">&#128722;</div>
            <p>Your cart is empty.</p>
            <Link to="/shop" className="btn btn-primary mt-1">Browse the Collection</Link>
          </div>
        )}
      </div>
    </section>
  );
}
