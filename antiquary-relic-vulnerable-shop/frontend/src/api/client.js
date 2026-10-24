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
  getArtifacts: (eraId) => request(`/artifacts${eraId ? `?era=${eraId}` : ""}`),
  getFeatured: () => request("/artifacts/featured"),
  getEras: () => request("/eras"),
  getArtifact: (id) => request(`/artifact?id=${id}`),
  search: (q) => request(`/search?q=${encodeURIComponent(q)}`),
  addProvenanceNote: (artifactId, note) =>
    request(`/artifacts/${artifactId}/provenance-note`, {
      method: "POST",
      body: JSON.stringify({ note }),
    }),
  getAppraisalQuote: (itemDescription) =>
    request(`/appraisal-request/quote?item_description=${encodeURIComponent(itemDescription)}`),
  submitAppraisalRequest: (email, itemDescription) =>
    request("/appraisal-request/submit", {
      method: "POST",
      body: JSON.stringify({ email, item_description: itemDescription }),
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

  getWishlist: () => request("/wishlist"),
  addToWishlist: (artifactId) => request(`/wishlist/${artifactId}/add`, { method: "POST" }),
  removeFromWishlist: (artifactId) => request(`/wishlist/${artifactId}/remove`, { method: "POST" }),

  getCart: () => request("/cart"),
  addToCart: (artifactId, qty) =>
    request(`/cart/add/${artifactId}`, { method: "POST", body: JSON.stringify({ qty }) }),
  removeFromCart: (artifactId) => request(`/cart/remove/${artifactId}`, { method: "POST" }),
  checkout: (address) =>
    request("/checkout", { method: "POST", body: JSON.stringify({ address }) }),
  getOrder: (id) => request(`/order/${id}`),

  saveShowroomPreferences: (preferences) =>
    request("/showroom-visit/save-preferences", {
      method: "POST",
      body: JSON.stringify({ preferences }),
    }),
  restoreShowroomPreferences: () => request("/showroom-visit/restore-preferences"),

  appraisalReportUrl: (filename) => `/api/appraisal-report/${filename}`,
  certificateUrl: (filename) => `/api/certificate/${filename}`,
  listAppraisals: () => request("/appraisals-list"),
  listUploads: () => request("/uploads-list"),

  adminStats: () => request("/admin/stats"),
  adminNewArtifact: (payload) =>
    request("/admin/artifacts", { method: "POST", body: JSON.stringify(payload) }),
  adminPingAuthenticator: (hostname) =>
    request("/admin/ping-authenticator", { method: "POST", body: JSON.stringify({ hostname }) }),
  adminGenerateConditionReport: (conditionCode) =>
    request("/admin/generate-condition-report", {
      method: "POST",
      body: JSON.stringify({ condition_code: conditionCode }),
    }),
  adminVerifyAuthenticatorCredential: (apiUrl) =>
    request("/admin/verify-authenticator-credential", {
      method: "POST",
      body: JSON.stringify({ api_url: apiUrl }),
    }),
  adminSystemInfo: () => request("/admin/system-info"),
  adminProvenanceDbDiagnostics: () => request("/admin/provenance-db-diagnostics"),
};
