import React, { useEffect, useState } from "react";
import { useParams, Link } from "react-router-dom";
import { api } from "../api/client.js";

export default function OrderConfirmationPage() {
  const { id } = useParams();
  const [order, setOrder] = useState(null);
  const [items, setItems] = useState([]);

  useEffect(() => {
    api.getOrder(id).then((data) => {
      setOrder(data.order);
      setItems(data.items);
    });
  }, [id]);

  if (!order) return <div className="container section">Loading...</div>;

  return (
    <section className="section text-center">
      <div className="container">
        <div style={{ fontSize: "3.5rem" }}>&#127881;</div>
        <h1>Thank You for Your Order!</h1>
        <p className="section-subtitle">Order #{order.id} has been placed and is {order.status}.</p>

        <div className="form-card" style={{ textAlign: "left" }}>
          <h3>Order Details</h3>
          {items.map((item) => (
            <div key={item.id} style={{ display: "flex", alignItems: "center", gap: 14, padding: "10px 0", borderBottom: "1px solid var(--line)" }}>
              <img src={`/images/products/${item.image}`} className="cart-item-thumb" alt="" />
              <div style={{ flex: 1 }}>{item.name} &times; {item.quantity}</div>
              <div>${(item.price_each * item.quantity).toFixed(2)}</div>
            </div>
          ))}
          <div className="summary-row summary-total mt-1">
            <span>Total</span><span>${parseFloat(order.total).toFixed(2)}</span>
          </div>
          {/*
            VULNERABILITY #14 - CWE-80 Stored XSS. The shipping address
            entered at checkout only passes through the backend's
            deliberately-partial partialXssFilter() (blocks <script> tags
            and the literal alert( call only) before being stored, and is
            rendered here via dangerouslySetInnerHTML -- so any non-
            script/non-alert HTML payload entered as an address (e.g.
            <img src=x onerror=...> or <svg onload=...>) persists and
            executes every time this order confirmation is viewed, by the
            customer or by any admin/support agent reviewing the order.
          */}
          <p className="mt-1" style={{ fontSize: ".85rem", color: "var(--slate)" }}>
            Shipping to: <span dangerouslySetInnerHTML={{ __html: order.shipping_address }} />
          </p>
        </div>

        <Link to="/shop" className="btn btn-primary mt-2">Continue Shopping</Link>
      </div>
    </section>
  );
}
