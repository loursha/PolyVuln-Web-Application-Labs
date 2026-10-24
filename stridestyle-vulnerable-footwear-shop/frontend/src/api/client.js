const BASE = "/api";

async function request(path, options = {}) {
  const res = await fetch(BASE + path, {
    credentials: "include",
    headers: { "Content-Type": "application/json", ...(options.headers || {}) },
    ...options,
  });
  const contentType = res.headers.get("content-type") || "";
  const data = contentType.includes("application/json") ? await res.json() : await res.text();
  if (!res.ok) {
    const err = new Error((data && data.error) || "Request failed");
    err.data = data;
    err.status = res.status;
    throw err;
  }
  return data;
}

export const api = {
  getProducts: (categoryId) =>
    request(`/products${categoryId ? `?category=${categoryId}` : ""}`),
  getFeatured: () => request("/products/featured"),
  getCategories: () => request("/categories"),
  getProduct: (id) => request(`/product?id=${id}`),
  search: (q) => request(`/search?q=${encodeURIComponent(q)}`),
  addReview: (productId, rating, comment) =>
    request(`/product/${productId}/review`, {
      method: "POST",
      body: JSON.stringify({ rating, comment }),
    }),

  login: (username, password, sid) =>
    request(`/login${sid ? `?sid=${encodeURIComponent(sid)}` : ""}`, {
      method: "POST",
      body: JSON.stringify({ username, password }),
    }),
  register: (username, email, password, isAdmin) =>
    request("/register", {
      method: "POST",
      body: JSON.stringify({ username, email, password, is_admin: isAdmin }),
    }),
  logout: () => request("/logout", { method: "POST" }),
  session: () => request("/session"),
  forgotPassword: (username) =>
    request("/forgot-password", { method: "POST", body: JSON.stringify({ username }) }),
  resetPassword: (username, token, password) =>
    request("/reset-password", {
      method: "POST",
      body: JSON.stringify({ username, token, password }),
    }),

  getAccount: () => request("/account"),
  getCsrfToken: () => request("/account/csrf-token"),
  updateEmail: (email, csrfToken) =>
    request("/account/update-email", {
      method: "POST",
      body: JSON.stringify({ email, csrf_token: csrfToken }),
    }),
  uploadAvatar: (file) => {
    const form = new FormData();
    form.append("avatar", file);
    return fetch(BASE + "/account/avatar", {
      method: "POST",
      credentials: "include",
      body: form,
    }).then((r) => r.json());
  },

  getCart: () => request("/cart"),
  addToCart: (productId, qty) =>
    request(`/cart/add/${productId}`, { method: "POST", body: JSON.stringify({ qty }) }),
  removeFromCart: (productId) => request(`/cart/remove/${productId}`, { method: "POST" }),
  saveCart: () => request("/cart/save", { method: "POST" }),
  restoreCart: () => request("/cart/restore"),
  checkout: (address) =>
    request("/checkout", { method: "POST", body: JSON.stringify({ address }) }),
  getOrder: (id) => request(`/order/${id}`),
  reorder: (id) => request(`/order/${id}/reorder`, { method: "POST" }),

  downloadInvoice: (filename) => `/api/download/invoice/${filename}`,
  avatarUrl: (filename) => `/api/avatar/${filename}`,
  listUploads: () => request("/uploads-list"),

  adminStats: () => request("/admin/stats"),
  adminNewProduct: (payload) =>
    request("/admin/products", { method: "POST", body: JSON.stringify(payload) }),
  adminPingSupplier: (hostname) =>
    request("/admin/ping-supplier", { method: "POST", body: JSON.stringify({ hostname }) }),
  adminExportSizeChart: (regionCode) =>
    request("/admin/export-size-chart", {
      method: "POST",
      body: JSON.stringify({ region_code: regionCode }),
    }),
  adminVerifyUnsubscribeWebhook: (webhookUrl) =>
    request("/newsletter/verify-unsubscribe-webhook", {
      method: "POST",
      body: JSON.stringify({ webhook_url: webhookUrl }),
    }),
  adminSystemInfo: () => request("/admin/system-info"),
  debugEnv: () => request("/debug/env"),

  subscribeNewsletter: (email) =>
    request("/newsletter/subscribe", { method: "POST", body: JSON.stringify({ email }) }),
};
