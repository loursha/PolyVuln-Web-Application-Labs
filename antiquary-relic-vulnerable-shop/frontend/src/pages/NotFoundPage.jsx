import React from "react";
import { Link } from "react-router-dom";

export default function NotFoundPage() {
  return (
    <div className="container section text-center">
      <div className="empty-state">
        <div className="icon">&#127961;</div>
        <h2>Page Not Found</h2>
        <p>The page you're looking for doesn't exist.</p>
        <Link to="/" className="btn btn-primary mt-1">Back to Home</Link>
      </div>
    </div>
  );
}
