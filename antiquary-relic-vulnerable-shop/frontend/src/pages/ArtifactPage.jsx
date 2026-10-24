import React, { useEffect, useState } from "react";
import { useSearchParams } from "react-router-dom";
import { api } from "../api/client.js";
import { useSession } from "../App.jsx";

export default function ArtifactPage() {
  const [params] = useSearchParams();
  const id = params.get("id") || "1";
  const [artifact, setArtifact] = useState(null);
  const [notes, setNotes] = useState([]);
  const [qty, setQty] = useState(1);
  const [note, setNote] = useState("");
  const [error, setError] = useState(null);
  const [inWishlist, setInWishlist] = useState(false);
  const session = useSession();

  const load = () => {
    api
      .getArtifact(id)
      .then((data) => {
        setArtifact(data.artifact);
        setNotes(data.notes);
        setError(null);
      })
      .catch((err) => setError(err.message));
  };

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  const addToCart = async () => {
    await api.addToCart(artifact.id, qty);
    session.refreshCart();
    session.showToast("Added to your cart.");
  };

  // VULNERABILITY #2 (CSRF) lives on the wishlist ADD endpoint on the
  // backend, not here in the UI -- this button itself is a normal,
  // intentional user action. The vulnerability is that the backend
  // accepts this same request from ANY origin with no CSRF token, so an
  // attacker's page can trigger it without the user ever clicking here.
  const toggleWishlist = async () => {
    if (!session?.username) {
      session.showToast("Please log in to save items to your wishlist.", "warning");
      return;
    }
    if (inWishlist) {
      await api.removeFromWishlist(artifact.id);
      setInWishlist(false);
    } else {
      await api.addToWishlist(artifact.id);
      setInWishlist(true);
      session.showToast("Added to your wishlist.");
    }
  };

  const submitNote = async (e) => {
    e.preventDefault();
    if (!session?.username) {
      session.showToast("Please log in to contribute a provenance note.", "warning");
      return;
    }
    await api.addProvenanceNote(artifact.id, note);
    setNote("");
    load();
    session.showToast("Thank you for your contribution.");
  };

  if (error) {
    return (
      <div className="container section">
        <div className="alert alert-danger">{error}</div>
      </div>
    );
  }
  if (!artifact) return <div className="container section">Loading...</div>;

  return (
    <section className="section">
      <div className="container">
        <div className="product-detail">
          <div className="thumb-large">
            <img src={`/images/products/${artifact.image}`} alt={artifact.name} />
          </div>
          <div className="product-detail-info">
            {!!artifact.featured && <span className="badge" style={{ position: "static", display: "inline-block", marginBottom: 12 }}>Featured</span>}
            <div style={{ display: "flex", alignItems: "flex-start", justifyContent: "space-between", gap: 16 }}>
              <h1>{artifact.name}</h1>
              <button className={`wishlist-heart ${inWishlist ? "active" : ""}`} onClick={toggleWishlist} title="Add to wishlist">
                {inWishlist ? "♥" : "♡"}
              </button>
            </div>
            <div className="product-price">${parseFloat(artifact.price).toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>
            <p>{artifact.description}</p>

            <div className="product-meta">
              <div><span>Origin</span>{artifact.origin}</div>
              <div><span>Condition</span>{artifact.condition_code}</div>
              <div><span>Available</span>{artifact.stock} in stock</div>
            </div>

            <div className="qty-form">
              <input
                type="number"
                min="1"
                max="5"
                value={qty}
                onChange={(e) => setQty(parseInt(e.target.value || 1))}
              />
              <button className="btn btn-primary" onClick={addToCart}>Add to Cart</button>
            </div>
          </div>
        </div>

        <h2 className="mt-3">Provenance History</h2>
        {notes.length === 0 ? (
          <p style={{ color: "var(--stone)" }}>No provenance notes on file yet.</p>
        ) : (
          notes.map((n) => (
            <div className="note-entry" key={n.id}>
              <div className="note-meta">
                Contributed by <strong>{n.username}</strong> &middot; {new Date(n.created_at).toLocaleDateString()}
              </div>
              {/*
                VULNERABILITY #14 - CWE-80 Stored XSS (see server.js
                partialXssFilter). The backend only strips <script> tags
                and the literal alert( call; every other tag/attribute
                (e.g. <img onerror>, <svg onload>) survives and is
                rendered here via dangerouslySetInnerHTML, exactly as
                returned by the API.
              */}
              <div dangerouslySetInnerHTML={{ __html: n.note }} />
            </div>
          ))
        )}

        <div className="form-card" style={{ margin: "30px 0 0" }}>
          <h3>Contribute a Provenance Note</h3>
          {session?.username ? (
            <form onSubmit={submitNote}>
              <div className="form-group">
                <label>Documented History</label>
                <textarea
                  value={note}
                  onChange={(e) => setNote(e.target.value)}
                  placeholder="Describe any ownership history, auction records, or authentication details you can document..."
                  required
                />
              </div>
              <button type="submit" className="btn btn-primary btn-block">Submit Note</button>
            </form>
          ) : (
            <p>Please <a href="/login">log in</a> to contribute a provenance note.</p>
          )}
        </div>
      </div>
    </section>
  );
}
