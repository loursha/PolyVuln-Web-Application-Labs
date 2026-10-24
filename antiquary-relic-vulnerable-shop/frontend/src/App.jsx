import React, { useEffect, useState, createContext, useContext } from "react";
import { Routes, Route } from "react-router-dom";
import { api } from "./api/client.js";

import Navbar from "./components/Navbar.jsx";
import Footer from "./components/Footer.jsx";

import HomePage from "./pages/HomePage.jsx";
import ShopPage from "./pages/ShopPage.jsx";
import ArtifactPage from "./pages/ArtifactPage.jsx";
import SearchPage from "./pages/SearchPage.jsx";
import AppraisalQuotePage from "./pages/AppraisalQuotePage.jsx";
import LoginPage from "./pages/LoginPage.jsx";
import RegisterPage from "./pages/RegisterPage.jsx";
import ForgotPasswordPage from "./pages/ForgotPasswordPage.jsx";
import ResetPasswordPage from "./pages/ResetPasswordPage.jsx";
import AccountPage from "./pages/AccountPage.jsx";
import WishlistPage from "./pages/WishlistPage.jsx";
import CartPage from "./pages/CartPage.jsx";
import CheckoutPage from "./pages/CheckoutPage.jsx";
import OrderConfirmationPage from "./pages/OrderConfirmationPage.jsx";
import AdminDashboardPage from "./pages/AdminDashboardPage.jsx";
import NotFoundPage from "./pages/NotFoundPage.jsx";

export const SessionContext = createContext(null);
export function useSession() {
  return useContext(SessionContext);
}

export default function App() {
  const [session, setSession] = useState({ username: null, isAdmin: false, loading: true });
  const [cartCount, setCartCount] = useState(0);
  const [toast, setToast] = useState(null);

  const refreshSession = () => {
    api
      .session()
      .then((s) => setSession({ ...s, loading: false }))
      .catch(() => setSession({ username: null, isAdmin: false, loading: false }));
  };

  const refreshCart = () => {
    api
      .getCart()
      .then((c) => setCartCount(c.items.reduce((sum, i) => sum + i.qty, 0)))
      .catch(() => setCartCount(0));
  };

  useEffect(() => {
    refreshSession();
    refreshCart();
  }, []);

  const showToast = (message, type = "success") => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 3500);
  };

  return (
    <SessionContext.Provider value={{ ...session, refreshSession, cartCount, refreshCart, showToast }}>
      <Navbar />
      {toast && (
        <div className="container mt-2">
          <div className={`alert alert-${toast.type}`}>{toast.message}</div>
        </div>
      )}
      <Routes>
        <Route path="/" element={<HomePage />} />
        <Route path="/shop" element={<ShopPage />} />
        <Route path="/artifact" element={<ArtifactPage />} />
        <Route path="/search" element={<SearchPage />} />
        <Route path="/appraisal-quote" element={<AppraisalQuotePage />} />
        <Route path="/login" element={<LoginPage />} />
        <Route path="/register" element={<RegisterPage />} />
        <Route path="/forgot-password" element={<ForgotPasswordPage />} />
        <Route path="/reset-password" element={<ResetPasswordPage />} />
        <Route path="/account" element={<AccountPage />} />
        <Route path="/wishlist" element={<WishlistPage />} />
        <Route path="/cart" element={<CartPage />} />
        <Route path="/checkout" element={<CheckoutPage />} />
        <Route path="/order/:id" element={<OrderConfirmationPage />} />
        <Route path="/admin" element={<AdminDashboardPage />} />
        <Route path="*" element={<NotFoundPage />} />
      </Routes>
      <Footer />
    </SessionContext.Provider>
  );
}
