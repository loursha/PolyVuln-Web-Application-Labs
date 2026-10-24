import React, { useState } from "react";
import { api } from "../api/client.js";
import { useSession } from "../App.jsx";

export default function AppraisalQuotePage() {
  const [itemDescription, setItemDescription] = useState("");
  const [quoteMessage, setQuoteMessage] = useState(null);
  const [email, setEmail] = useState("");
  const session = useSession();

  const getQuote = async (e) => {
    e.preventDefault();
    const res = await api.getAppraisalQuote(itemDescription);
    setQuoteMessage(res.quote_message);
  };

  const submitFullRequest = async (e) => {
    e.preventDefault();
    await api.submitAppraisalRequest(email, itemDescription);
    session.showToast("Your appraisal request has been submitted. We'll follow up by email.");
    setEmail("");
  };

  return (
    <section className="section">
      <div className="container">
        <span className="eyebrow">Considering Selling?</span>
        <div className="section-head">
          <h1 className="section-title">Free Instant Appraisal Quote</h1>
          <p className="section-subtitle">Describe your item and get a ballpark estimate in seconds.</p>
        </div>

        <div className="form-card">
          <form onSubmit={getQuote}>
            <div className="form-group">
              <label>Describe Your Item</label>
              <textarea
                value={itemDescription}
                onChange={(e) => setItemDescription(e.target.value)}
                placeholder="e.g. a Victorian writing desk with brass fittings..."
                required
              />
            </div>
            <button type="submit" className="btn btn-primary btn-block">Get My Free Quote</button>
          </form>

          {quoteMessage && (
            <div className="alert alert-info mt-2">
              {/*
                VULNERABILITY #13 - CWE-80 Reflected XSS (see server.js
                partialXssFilter). The backend only strips <script> tags
                and the literal alert( call before echoing the item
                description back inside quote_message; every other
                tag/attribute survives and is rendered here via
                dangerouslySetInnerHTML.
              */}
              <span dangerouslySetInnerHTML={{ __html: quoteMessage }} />
            </div>
          )}

          {quoteMessage && (
            <form onSubmit={submitFullRequest} className="mt-2">
              <div className="form-group">
                <label>Email Address (to receive your full written appraisal)</label>
                <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
              </div>
              <button type="submit" className="btn btn-outline btn-block">Request Full Written Appraisal</button>
            </form>
          )}
        </div>
      </div>
    </section>
  );
}
