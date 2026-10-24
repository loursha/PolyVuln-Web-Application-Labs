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
        <div style={{ fontSize: "3.5rem" }}>&#127961;</div>
        <h1>Thank You for Your Acquisition</h1>
        <p className="section-subtitle">Order #{order.id} has been placed and is {order.status}.</p>

        <div className="form-card" style={{ textAlign: "left" }}>
          <h3>Order Details</h3>
          {items.map((item) => (
            <div key={item.id} style={{ display: "flex", alignItems: "center", gap: 14, padding: "10px 0", borderBottom: "1px solid var(--parchment-dark)" }}>
              <img src={`/images/products/${item.image}`} className="cart-item-thumb" alt="" />
              <div style={{ flex: 1 }}>{item.name} &times; {item.quantity}</div>
              <div>${(item.price_each * item.quantity).toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>
            </div>
          ))}
          <div className="summary-row summary-total mt-1">
            <span>Total</span><span>${parseFloat(order.total).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
          </div>
          {/*
            The shipping address is rendered as ordinary React text
            (auto-escaped), so this is NOT an XSS sink in this build --
            unlike the shipping-address sink used in this lab's earlier
            footwear-shop build. Stored XSS here lives on provenance
            notes instead (see ArtifactPage.jsx).
          */}
          <p className="mt-1" style={{ fontSize: ".85rem", color: "var(--stone)" }}>
            Shipping to: {order.shipping_address}
          </p>
        </div>

        <Link to="/shop" className="btn btn-primary mt-2">Continue Browsing</Link>
      </div>
    </section>
  );
}
