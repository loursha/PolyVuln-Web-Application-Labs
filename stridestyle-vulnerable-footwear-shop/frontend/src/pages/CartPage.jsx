import React, { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { api } from "../api/client.js";
import { useSession } from "../App.jsx";

export default function CartPage() {
  const [items, setItems] = useState([]);
  const [total, setTotal] = useState(0);
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

  const saveForLater = async () => {
    await api.saveCart();
    session.showToast("Cart saved! It'll be here next time.", "info");
  };

  const restoreCart = async () => {
    try {
      await api.restoreCart();
      session.refreshCart();
      load();
      session.showToast("Your saved cart has been restored.");
    } catch (err) {
      session.showToast("Could not restore saved cart.", "danger");
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
                <tr><th></th><th>Product</th><th>Price</th><th>Qty</th><th>Subtotal</th><th></th></tr>
              </thead>
              <tbody>
                {items.map((item) => (
                  <tr key={item.product.id}>
                    <td><img className="cart-item-thumb" src={`/images/products/${item.product.image}`} alt="" /></td>
                    <td>{item.product.name}</td>
                    <td>${parseFloat(item.product.price).toFixed(2)}</td>
                    <td>{item.qty}</td>
                    <td>${item.subtotal.toFixed(2)}</td>
                    <td>
                      <button className="btn btn-sm btn-outline" onClick={() => removeItem(item.product.id)}>
                        Remove
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>

            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", margin: "10px 0 30px" }}>
              <button className="btn btn-outline btn-sm" onClick={saveForLater}>Save Cart for Later</button>
              <button className="btn btn-outline btn-sm" onClick={restoreCart}>Restore Saved Cart</button>
            </div>

            <div className="cart-summary">
              <div className="summary-row"><span>Subtotal</span><span>${total.toFixed(2)}</span></div>
              <div className="summary-row"><span>Shipping</span><span>Free</span></div>
              <div className="summary-row summary-total"><span>Total</span><span>${total.toFixed(2)}</span></div>
              <Link to="/checkout" className="btn btn-primary btn-block mt-1">Proceed to Checkout</Link>
            </div>
          </>
        ) : (
          <div className="empty-state">
            <div className="icon">&#128722;</div>
            <p>Your cart is empty.</p>
            <Link to="/shop" className="btn btn-primary mt-1">Browse Footwear</Link>
          </div>
        )}
      </div>
    </section>
  );
}
